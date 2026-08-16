<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BakongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected BakongService $bakongService;

    public function __construct(BakongService $bakongService)
    {
        $this->bakongService = $bakongService;
    }

    /**
     * Generate Bakong KHQR Payload & Payment Attempt
     */
    public function generateKhqr(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|in:USD,KHR',
            'sale_id' => 'nullable|string',
            'bill_number' => 'nullable|string',
        ]);

        $amount = (float) $validated['amount'];
        $currency = strtoupper($validated['currency'] ?? 'USD');
        $billNumber = $validated['bill_number'] ?? ('BILL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        $payload = $this->bakongService->generateKhqrPayload($amount, $currency, $billNumber);

        $paymentId = (string) Str::uuid();
        $attemptId = (string) Str::uuid();

        // Record Header Payment
        DB::table('payments')->insert([
            'id' => $paymentId,
            'sale_id' => $validated['sale_id'] ?? (string) Str::uuid(),
            'tender_type' => 'khqr',
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'reference_number' => $billNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Record Payment Attempt
        DB::table('payment_attempts')->insert([
            'id' => $attemptId,
            'payment_id' => $paymentId,
            'merchant_reference' => $billNumber,
            'provider_transaction_id' => $payload['md5'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'raw_payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'payment_id' => $paymentId,
                'attempt_id' => $attemptId,
                'merchant_reference' => $billNumber,
                'md5' => $payload['md5'],
                'khqr_string' => $payload['khqr_string'],
                'amount' => $amount,
                'currency' => $currency,
                'khr_equivalent' => $payload['khr_equivalent'],
                'merchant_name' => $payload['merchant_name'],
                'merchant_city' => $payload['merchant_city'],
            ],
        ], 201);
    }

    /**
     * Check payment status against database and NBC Bakong Open API
     */
    public function checkStatus(Request $request, $id)
    {
        $attempt = DB::table('payment_attempts')->where('id', $id)->first();
        if (!$attempt) {
            $attempt = DB::table('payment_attempts')->where('merchant_reference', $id)->first();
        }
        if (!$attempt) {
            $attempt = DB::table('payment_attempts')->where('payment_id', $id)->first();
        }

        if (!$attempt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment attempt record not found.',
            ], 404);
        }

        if ($attempt->status === 'paid') {
            return response()->json([
                'status' => 'success',
                'paid' => true,
                'attempt' => $attempt,
            ]);
        }

        // Query Bakong Open API with MD5 hash
        $md5Hash = $attempt->provider_transaction_id;
        $bakongRes = $this->bakongService->checkTransactionByMd5($md5Hash);

        if ($bakongRes['paid']) {
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);

            DB::table('payments')->where('id', $attempt->payment_id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);

            $attempt->status = 'paid';
        }

        return response()->json([
            'status' => 'success',
            'paid' => $attempt->status === 'paid',
            'bakong_verification' => $bakongRes,
            'attempt' => $attempt,
        ]);
    }

    /**
     * Simulate Sandbox Payment Approval for local testing
     */
    public function simulatePay(Request $request, $id)
    {
        $attempt = DB::table('payment_attempts')->where('id', $id)->first();
        if (!$attempt) {
            $attempt = DB::table('payment_attempts')->where('payment_id', $id)->first();
        }

        if (!$attempt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment attempt not found.',
            ], 404);
        }

        DB::table('payment_attempts')->where('id', $attempt->id)->update([
            'status' => 'paid',
            'updated_at' => now(),
        ]);

        DB::table('payments')->where('id', $attempt->payment_id)->update([
            'status' => 'paid',
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment simulated & approved successfully.',
            'paid' => true,
        ]);
    }
}
