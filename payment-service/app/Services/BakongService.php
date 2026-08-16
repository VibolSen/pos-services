<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BakongService
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $merchantName;
    protected string $merchantCity;
    protected string $merchantId;

    public function __construct()
    {
        $this->apiUrl = config('services.bakong.url', 'https://api-bakong.nbc.gov.kh/v1');
        $this->apiToken = config('services.bakong.token', '');
        $this->merchantName = config('services.bakong.merchant_name', 'Freshmart POS');
        $this->merchantCity = config('services.bakong.merchant_city', 'Phnom Penh');
        $this->merchantId = config('services.bakong.merchant_id', 'freshmart_pp');
    }

    /**
     * Generate Bakong KHQR payload and transaction MD5 reference
     */
    public function generateKhqrPayload(float $amount, string $currency = 'USD', string $billNumber = ''): array
    {
        $currencyCode = strtoupper($currency) === 'KHR' ? '116' : '840';
        $timestamp = time();
        $billNo = $billNumber ?: 'BILL-' . strtoupper(substr(uniqid(), -6));
        
        // Generate MD5 Hash identifier for Bakong API transaction lookup
        $rawMd5String = strtolower("{$this->merchantId}{$amount}{$currencyCode}{$billNo}{$timestamp}");
        $md5Hash = md5($rawMd5String);

        // Standard Bakong EMVCo KHQR String Structure
        $khrAmount = strtoupper($currency) === 'USD' ? round($amount * 4100) : $amount;
        $khqrString = "00020101021230480016" . str_pad($this->merchantId, 16, '0', STR_PAD_RIGHT) .
            "520459995303" . $currencyCode . "5406" . sprintf("%06.2f", $amount) .
            "5802KH59" . str_pad(strlen($this->merchantName), 2, '0', STR_PAD_LEFT) . $this->merchantName .
            "60" . str_pad(strlen($this->merchantCity), 2, '0', STR_PAD_LEFT) . $this->merchantCity .
            "62" . str_pad(strlen($billNo) + 4, 2, '0', STR_PAD_LEFT) . "07" . str_pad(strlen($billNo), 2, '0', STR_PAD_LEFT) . $billNo .
            "6304" . strtoupper(substr($md5Hash, 0, 4));

        return [
            'khqr_string' => $khqrString,
            'md5' => $md5Hash,
            'merchant_name' => $this->merchantName,
            'merchant_city' => $this->merchantCity,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'khr_equivalent' => $khrAmount,
            'bill_number' => $billNo,
        ];
    }

    /**
     * Verify transaction completion using Bakong Open API check_transaction_by_md5
     */
    public function checkTransactionByMd5(string $md5Hash): array
    {
        if (empty($this->apiToken)) {
            Log::warning('Bakong API Token not configured.');
            return [
                'paid' => false,
                'response_code' => 1,
                'message' => 'Bakong API Token missing',
            ];
        }

        try {
            $endpoint = "{$this->apiUrl}/check_transaction_by_md5";
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->post($endpoint, [
                    'md5' => $md5Hash,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                // Bakong responseCode 0 indicates transaction found and paid
                $isPaid = isset($data['responseCode']) && (int) $data['responseCode'] === 0;

                return [
                    'paid' => $isPaid,
                    'response_code' => $data['responseCode'] ?? 1,
                    'response_message' => $data['responseMessage'] ?? 'Unknown status',
                    'data' => $data['data'] ?? null,
                    'raw' => $data,
                ];
            }

            return [
                'paid' => false,
                'response_code' => $response->status(),
                'response_message' => 'Bakong API request failed',
                'raw' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('Bakong API error: ' . $e->getMessage());
            return [
                'paid' => false,
                'response_code' => 500,
                'response_message' => $e->getMessage(),
            ];
        }
    }
}
