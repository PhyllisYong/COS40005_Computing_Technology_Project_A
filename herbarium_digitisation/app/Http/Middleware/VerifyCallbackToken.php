<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackToken
{
    /**
     * Service auth disabled for local development.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('VerifyCallbackToken: service auth is disabled; callback accepted.', [
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
