<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Payment Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'payment-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Webhook Callbacks (ABA PayWay / KHQR)
    Route::post('/payment-callbacks/aba', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment callback received',
            'data' => $request->all(),
        ]);
    });

    // Protected Payment & Refund Operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('role:cashier,supervisor,outlet_manager,admin,super_admin')->group(function () {
            Route::post('/payments', function (Request $request) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment attempt created',
                    'merchant_reference' => 'PAY-' . strtoupper(uniqid()),
                ]);
            });

            Route::get('/payments/{id}/status', function ($id) {
                return response()->json([
                    'status' => 'success',
                    'payment_status' => 'paid',
                ]);
            });

            Route::post('/sales/{id}/refund', function (Request $request, $id) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Refund processed successfully for sale #' . $id,
                ]);
            });
        });
    });
});

