<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\BrandController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Catalog Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'catalog-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public / Read-Only Catalog Endpoints
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/barcodes/{code}', [ProductController::class, 'showByBarcode']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/brands', [BrandController::class, 'index']);

    // Protected Catalog Management Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Product Management (Restricted to Inventory Clerk, Outlet Manager, Admin, Super Admin)
        Route::middleware('role:inventory_clerk,outlet_manager,admin,super_admin')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::post('/products/bulk', [ProductController::class, 'bulkStore']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);

            // Categories Management
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

            // Brands Management
            Route::post('/brands', [BrandController::class, 'store']);
            Route::put('/brands/{id}', [BrandController::class, 'update']);
            Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
        });
    });
});

