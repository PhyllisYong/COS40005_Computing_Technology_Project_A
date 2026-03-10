<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackToken
{
    /**
     * Reject requests that do not carry the expected bearer token.
     * This protects the internal callback endpoint from arbitrary callers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.leafmachine2.callback_token');

        // If no token is configured, deny all requests
        if (empty($expected)) {
            Log::error('VerifyCallbackToken: LM2 callback token not configured.');
            abort(500, 'Callback authentication not configured.');
        }

        $authHeader = $request->header('Authorization', '');
        $provided   = str_starts_with($authHeader, 'Bearer ')
            ? substr($authHeader, 7)
            : '';

        // Use constant-time comparison to prevent timing attacks
        if (!hash_equals($expected, $provided)) {
            Log::warning('VerifyCallbackToken: invalid bearer token', [
                'ip' => $request->ip(),
            ]);
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
