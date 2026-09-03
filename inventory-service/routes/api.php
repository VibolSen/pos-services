<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\PurchaseController;

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
        // Read-only stock queries accessible by all authenticated organization staff
        Route::get('/inventory/balances', [InventoryController::class, 'balances']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);
        Route::get('/inventory/expired', [InventoryController::class, 'expired']);
        Route::get('/inventory/transfers', [TransferController::class, 'index']);
        Route::get('/inventory/transfers/{id}', [TransferController::class, 'show']);
        Route::get('/purchases', [PurchaseController::class, 'purchases']);
        Route::get('/purchase-orders', [PurchaseController::class, 'purchaseOrders']);

        // Stock Mutations (Write Operations - Guarded by Manager/Clerk Roles)
        Route::middleware('role:admin,super_admin,outlet_manager,inventory_clerk')->group(function () {
            Route::post('/inventory/receive', [InventoryController::class, 'receive']);
            Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
            Route::post('/inventory/transfers', [TransferController::class, 'store']);
            Route::post('/inventory/transfers/{id}/receive', [TransferController::class, 'receive']);
        });
    });
});
