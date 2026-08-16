<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\FinanceController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Payment Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'payment-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Webhook Callbacks (Bakong / ABA PayWay / KHQR)
    Route::post('/payment-callbacks/aba', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment callback received',
            'data' => $request->all(),
        ]);
    });

    // Protected Payment Operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('role:cashier,supervisor,outlet_manager,admin,super_admin,accountant')->group(function () {
            Route::post('/payments/khqr/generate', [PaymentController::class, 'generateKhqr']);
            Route::get('/payments/{id}/status', [PaymentController::class, 'checkStatus']);
            Route::post('/payments/{id}/simulate-pay', [PaymentController::class, 'simulatePay']);

            // Payment Reconciliation & Exception Auditing
            Route::post('/reconciliation/run', [ReconciliationController::class, 'run']);
            Route::get('/reconciliation/exceptions', [ReconciliationController::class, 'exceptions']);
            Route::post('/reconciliation/exceptions/{id}/resolve', [ReconciliationController::class, 'resolveException']);

            // Expenses, Income & Bank Accounts
            Route::get('/expenses', [FinanceController::class, 'expenses']);
            Route::get('/income', [FinanceController::class, 'incomes']);
            Route::get('/bank-accounts', [FinanceController::class, 'bankAccounts']);
        });
    });
});
