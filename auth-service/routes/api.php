<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerLoyaltyController;
use App\Http\Controllers\Api\V1\OutletController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\GiftCardController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\ApiKeyController;

Route::prefix('v1')->group(function () {
    // Health Check Endpoint for Auth Microservice
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'auth-service',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Auth Routes (with Brute-Force Rate Limiting)
    Route::middleware('auth_throttle:5,15')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/quick-switch', [AuthController::class, 'quickSwitch']);
    });
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Staff Invitation Verification & Acceptance (Public)
    Route::get('/auth/verify-invite', [AuthController::class, 'verifyInvite']);
    Route::post('/auth/accept-invite', [AuthController::class, 'acceptInvite']);

    // Public Tenant Self-Registration
    Route::post('/tenants/register', [TenantController::class, 'register']);

    // Protected Auth & Management API Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Active Devices & Session Management
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{id}', [AuthController::class, 'revokeSession']);
        Route::post('/auth/logout-all-devices', [AuthController::class, 'logoutAllDevices']);
        Route::post('/auth/2fa/toggle', [AuthController::class, 'toggle2fa']);

        // Subscription Quota Usage
        Route::get('/tenants/quota-usage', [TenantController::class, 'quotaUsage']);

        // Developer / Merchant API Keys
        Route::get('/api-keys', [ApiKeyController::class, 'index']);
        Route::post('/api-keys', [ApiKeyController::class, 'store']);
        Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
        Route::put('/api-keys/{id}/toggle', [ApiKeyController::class, 'toggle']);

        // User & Staff Dynamic RBAC Management
        Route::middleware('role:admin,super_admin,outlet_manager')->group(function () {
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::get('/roles/{id}', [RoleController::class, 'show']);
            Route::put('/roles/{id}', [RoleController::class, 'update']);
            Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

            Route::get('/permissions', [PermissionController::class, 'index']);

            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/gift-cards', [GiftCardController::class, 'index']);
            Route::post('/gift-cards', [GiftCardController::class, 'store']);

            // User management with Quota Enforcement
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/roles/permissions', [UserController::class, 'permissions']);
            Route::post('/users', [UserController::class, 'store'])->middleware('quota:users');
            Route::post('/users/invite', [UserController::class, 'invite'])->middleware('quota:users');
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);

            // Suppliers CRUD
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::post('/suppliers', [SupplierController::class, 'store']);
            Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
            Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

            // Customers CRUD & Loyalty
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::put('/customers/{id}', [CustomerController::class, 'update']);
            Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
            Route::post('/customers/{id}/store-credit', [CustomerLoyaltyController::class, 'adjustStoreCredit']);
            Route::post('/customers/{id}/loyalty', [CustomerLoyaltyController::class, 'adjustLoyaltyPoints']);
            Route::post('/customers/{id}/convert-points', [CustomerLoyaltyController::class, 'convertPointsToCredit']);
            Route::get('/customers/{id}/history', [CustomerLoyaltyController::class, 'getHistory']);

            // Outlets CRUD with Quota Enforcement
            Route::get('/outlets', [OutletController::class, 'index']);
            Route::post('/outlets', [OutletController::class, 'store'])->middleware('quota:outlets');
            Route::put('/outlets/{id}', [OutletController::class, 'update']);
            Route::delete('/outlets/{id}', [OutletController::class, 'destroy']);

            // Departments CRUD
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::put('/departments/{id}', [DepartmentController::class, 'update']);
            Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

            // Employees CRUD
            Route::get('/employees', [EmployeeController::class, 'index']);
            Route::post('/employees', [EmployeeController::class, 'store']);
            Route::put('/employees/{id}', [EmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
        });

        // Supervisor PIN Verification Override
        Route::post('/auth/verify-pin', [UserController::class, 'verifyPin']);

        // Super Admin Tenant Management Routes
        Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
            Route::get('/tenants', [TenantController::class, 'index']);
            Route::get('/tenants/{id}', [TenantController::class, 'show']);
            Route::put('/tenants/{id}/subscription', [TenantController::class, 'updateSubscription']);
            Route::post('/tenants/{id}/suspend', [TenantController::class, 'suspend']);
        });
    });
});
