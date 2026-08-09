<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\InventoryController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Inventory Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'inventory-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Protected Inventory Operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('role:admin,super_admin,outlet_manager,inventory_clerk')->group(function () {
            Route::get('/inventory/balances', [InventoryController::class, 'balances']);
            Route::get('/inventory/movements', [InventoryController::class, 'movements']);
            Route::get('/inventory/expired', [InventoryController::class, 'expired']);
            Route::post('/inventory/receive', [InventoryController::class, 'receive']);
            Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
        });
    });
});

