<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyOcrCallbackToken
{
    /**
     * Service auth disabled for local development.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('VerifyOcrCallbackToken: service auth is disabled; callback accepted.', [
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
