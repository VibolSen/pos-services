<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\OnlineOrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\OfflineSyncController;
use App\Http\Controllers\Api\V1\KdsController;
use App\Http\Controllers\Api\V1\TableController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Sales Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'sales-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Customer E-commerce Order Creation
    Route::post('/online-orders', [OnlineOrderController::class, 'store']);

    // Protected Sales & Order Fulfillment Operations
    Route::middleware('auth:sanctum')->group(function () {
        // Sales Checkout, Receipts, Returns, Refunds, KDS & Tables
        Route::middleware('role:cashier,supervisor,outlet_manager,admin,super_admin,accountant,inventory_clerk,user,employee')->group(function () {
            Route::get('/sales', [CheckoutController::class, 'index']);
            Route::post('/sales', [CheckoutController::class, 'store']);
            Route::post('/sales/sync', [OfflineSyncController::class, 'sync']);
            Route::get('/sales/{id}/receipt', [ReceiptController::class, 'show']);
            Route::post('/sales/{id}/return-quote', [RefundController::class, 'returnQuote']);
            Route::post('/sales/{id}/refund', [RefundController::class, 'refund']);

            // Kitchen Display System (KDS) & Restaurant Tables
            Route::get('/kds/tickets', [KdsController::class, 'index']);
            Route::patch('/kds/tickets/{id}/status', [KdsController::class, 'updateStatus']);
            Route::get('/tables', [TableController::class, 'index']);
            Route::patch('/tables/{id}/status', [TableController::class, 'updateStatus']);

            // Online Order Fulfillment Dashboard
            Route::get('/online-orders', [OnlineOrderController::class, 'index']);
            Route::patch('/online-orders/{id}/status', [OnlineOrderController::class, 'updateStatus']);
        });

        // Cart Holding & Resuming
        Route::post('/carts/hold', [CartController::class, 'hold']);
        Route::get('/carts/held', [CartController::class, 'held']);
        Route::post('/carts/held/{id}/resume', [CartController::class, 'resume']);
        Route::delete('/carts/held/{id}', [CartController::class, 'destroy']);

        // Admin Dashboard & Financial Reporting Analytics
        Route::middleware('role:admin,super_admin,outlet_manager,accountant,cashier,supervisor,inventory_clerk,customer,user,employee')->group(function () {
            Route::get('/admin/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('/admin/dashboard/widgets', [DashboardController::class, 'widgets']);
            Route::get('/admin/dashboard/charts', [DashboardController::class, 'charts']);

            // Financial Reports & CSV Export
            Route::get('/reports/sales', [ReportController::class, 'sales']);
            Route::get('/reports/shifts', [ReportController::class, 'shifts']);
            Route::get('/reports/tax', [ReportController::class, 'tax']);
            Route::get('/reports/export', [ReportController::class, 'export']);
        });
    });
});
