<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Exception;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
            'register_id' => 'required|integer',
            'shift_id' => 'nullable|integer',
            'idempotency_key' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'tender_type' => 'required|string',
            'currency' => 'nullable|string|in:USD,KHR',
            'tax_total' => 'nullable|numeric|min:0',
            'discount_total' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->checkoutService->finalizeSale($validated, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $result['is_duplicate'] ? 'Sale already processed' : 'Sale completed successfully',
                'data' => $result,
            ], $result['is_duplicate'] ? 200 : 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
