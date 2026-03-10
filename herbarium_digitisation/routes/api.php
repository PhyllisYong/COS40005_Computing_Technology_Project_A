<?php

use App\Http\Controllers\Api\DigitisationJobCallbackController;
use App\Http\Middleware\VerifyCallbackToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal Callback Routes
|--------------------------------------------------------------------------
|
| These routes are called by the LeafMachine2 microservice, not by a
| browser session.  They are protected by a shared bearer token only
| (VerifyCallbackToken middleware) — no CSRF or session auth.
|
*/

Route::middleware(VerifyCallbackToken::class)
    ->prefix('internal/leafmachine/jobs')
    ->group(function () {
        Route::post('{jobId}/status', [DigitisationJobCallbackController::class, 'status'])
            ->name('lm2.callback.status');
    });
