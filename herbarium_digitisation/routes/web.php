<?php

use App\Http\Controllers\DigitisationController;
use App\Http\Controllers\DigitisationResultController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'dashboard')->name('home');
Route::inertia('/identification', 'identification')->name('identification');
Route::inertia('/digitalisation1', 'digitalisation1')->name('digitalisation1');

// ─── Digitisation ────────────────────────────────────────────────────────────
Route::get('/digitalisation', [DigitisationController::class, 'index'])
    ->name('digitalisation');
Route::post('/digitalisation', [DigitisationController::class, 'store'])
    ->name('digitalisation.store');

// Result file proxy-download
Route::get('/digitalisation/{job}/results/{filename}', [DigitisationResultController::class, 'download'])
    ->where('filename', '.+')
    ->name('digitalisation.results.download');

// JSON preview and import endpoints (consumed by the frontend via fetch)
Route::get('/api/digitisation/{job}/results/json', [DigitisationResultController::class, 'show'])
    ->name('digitisation.results.show');
Route::post('/api/digitisation/{job}/results', [DigitisationResultController::class, 'store'])
    ->name('digitisation.results.store');

require __DIR__.'/settings.php';
