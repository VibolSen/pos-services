<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\DashboardController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Sales Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'sales-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Protected Sales Operations
    Route::middleware('auth:sanctum')->group(function () {
        // Sales Checkout (Restricted to Cashier, Supervisor, Manager, Admin, Super Admin)
        Route::middleware('role:cashier,supervisor,outlet_manager,admin,super_admin')->group(function () {
            Route::post('/sales', [CheckoutController::class, 'store']);
        });

        // Cart Holding & Resuming
        Route::post('/carts/hold', [CartController::class, 'hold']);
        Route::get('/carts/held', [CartController::class, 'held']);
        Route::post('/carts/held/{id}/resume', [CartController::class, 'resume']);
        Route::delete('/carts/held/{id}', [CartController::class, 'destroy']);

        // Admin Dashboard Sales Analytics
        Route::middleware('role:admin,super_admin,outlet_manager,accountant')->group(function () {
            Route::get('/admin/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('/admin/dashboard/widgets', [DashboardController::class, 'widgets']);
            Route::get('/admin/dashboard/charts', [DashboardController::class, 'charts']);
        });
    });
});

