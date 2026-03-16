<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyIqcCallbackToken
{
    /**
     * Reject requests that do not carry the expected bearer token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.image_quality_check.callback_token');

        if (empty($expected)) {
            Log::error('VerifyIqcCallbackToken: IQC callback token not configured.');
            abort(500, 'Callback authentication not configured.');
        }

        $authHeader = $request->header('Authorization', '');
        $provided = str_starts_with($authHeader, 'Bearer ')
            ? substr($authHeader, 7)
            : '';

        if (!hash_equals($expected, $provided)) {
            Log::warning('VerifyIqcCallbackToken: invalid bearer token', [
                'ip' => $request->ip(),
            ]);
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
