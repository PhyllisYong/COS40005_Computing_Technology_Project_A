<?php

use App\Http\Controllers\DigitisationController;
use App\Http\Controllers\DigitisationResultController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\PredictController;

Route::inertia('/', 'dashboard')->name('dashboard');
Route::inertia('/identification', 'identification')->name('identification');
Route::inertia('/digitalisation1', 'digitalisation1')->name('digitalisation1');


// identification service route
Route::post('/api/identify', [PredictController::class, 'identify']);
Route::post('/api/heatmap', [PredictController::class, 'heatmap']);
// ─── Digitisation ────────────────────────────────────────────────────────────
Route::get('/digitalisation', [DigitisationController::class, 'index'])
    ->name('digitalisation');
Route::post('/digitalisation', [DigitisationController::class, 'store'])
    ->name('digitalisation.store');
Route::get('/api/digitisation/jobs/{externalJobId}/status', [DigitisationController::class, 'status'])
    ->name('digitisation.jobs.status');
Route::post('/api/digitisation/jobs/{externalJobId}/submit', [DigitisationController::class, 'submitAcceptedBatch'])
    ->name('digitisation.jobs.submit');

// Result file proxy-download
Route::get('/digitalisation/{job}/results/{filename}', [DigitisationResultController::class, 'download'])
    ->where('filename', '.+')
    ->name('digitalisation.results.download');

// JSON preview and import endpoints (consumed by the frontend via fetch)
Route::get('/api/digitisation/{job}/results/json', [DigitisationResultController::class, 'show'])
    ->name('digitisation.results.show');
Route::post('/api/digitisation/{job}/results', [DigitisationResultController::class, 'store'])
    ->name('digitisation.results.store');

Route::inertia('/register', 'auth/register')->name('register');
Route::inertia('/login', 'auth/login')->name('login');
require __DIR__.'/settings.php';
