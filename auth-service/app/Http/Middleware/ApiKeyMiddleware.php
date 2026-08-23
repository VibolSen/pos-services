<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request with an X-API-Key header.
     *
     * @param  \Closure(Request): (Response)  $next
     * @param  string|null  $permissionRequired
     */
    public function handle(Request $request, Closure $next, ?string $permissionRequired = null): Response
    {
        $rawKey = $request->header('X-API-Key') ?? $request->bearerToken();

        if (empty($rawKey)) {
            return response()->json([
                'status' => 'error',
                'code' => 'API_KEY_MISSING',
                'message' => 'Missing Merchant API Key. Please provide X-API-Key header.',
            ], 401);
        }

        $hashedSecret = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 12);

        $apiKey = DB::table('api_keys')
            ->where('secret_hash', $hashedSecret)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_API_KEY',
                'message' => 'Invalid, revoked, or expired Merchant API Key.',
            ], 401);
        }

        // Check specific permission if specified
        if ($permissionRequired && !empty($apiKey->permissions)) {
            $permissions = json_decode($apiKey->permissions, true) ?: [];
            if (!in_array('*', $permissions) && !in_array($permissionRequired, $permissions)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => "This API key does not have the required '{$permissionRequired}' permission.",
                ], 403);
            }
        }

        // Touch last_used_at
        DB::table('api_keys')->where('id', $apiKey->id)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);

        $request->attributes->set('merchant_api_key', $apiKey);
        $request->attributes->set('tenant_id', $apiKey->tenant_id);

        return $next($request);
    }
}
