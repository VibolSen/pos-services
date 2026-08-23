<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitAuthMiddleware
{
    /**
     * Handle rate limiting for sensitive authentication endpoints (brute-force defense).
     *
     * @param  \Closure(Request): (Response)  $next
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decayMinutes = 15): Response
    {
        $ip = $request->ip();
        $email = $request->input('email') ?? $request->input('pin_code') ?? 'guest';
        $throttleKey = 'auth_attempt:' . sha1($ip . '|' . $email);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            return response()->json([
                'status' => 'error',
                'code' => 'ACCOUNT_LOCKED_OUT',
                'message' => "Too many failed attempts. Security cooldown active. Please try again in {$minutes} minute(s) ({$seconds} seconds).",
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        $response = $next($request);

        // If response indicates a 401 or 422 authentication error, record the hit
        if ($response->getStatusCode() === 401 || ($response->getStatusCode() === 422 && $request->is('*/auth/login', '*/auth/quick-switch', '*/auth/verify-pin'))) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);
        } elseif ($response->isSuccessful()) {
            RateLimiter::clear($throttleKey);
        }

        return $response;
    }
}
