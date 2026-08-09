<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ShiftController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Shift Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'shift-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Protected Shift & Cash Drawer Operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/shifts/active', [ShiftController::class, 'active']);
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::post('/shifts/{id}/cash-movement', [ShiftController::class, 'cashMovement']);
        Route::post('/shifts/{id}/close', [ShiftController::class, 'close']);
    });
});

