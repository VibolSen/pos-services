<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Auth Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'auth-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected Auth & User RBAC API Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // User & Staff RBAC Management (Restricted to Admin & Super Admin)
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/roles/permissions', [UserController::class, 'permissions']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
        });

        // Supervisor PIN Verification Override
        Route::post('/auth/verify-pin', [UserController::class, 'verifyPin']);
    });
});

