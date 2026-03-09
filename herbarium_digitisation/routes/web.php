<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\PredictController;

Route::inertia('/', 'dashboard')->name('dashboard');
Route::inertia('/identification', 'identification')->name('identification');
Route::inertia('/digitalisation', 'digitalisation')->name('digitalisation');
Route::inertia('/digitalisation1', 'digitalisation1')->name('digitalisation1');


// identification service route
Route::post('/api/identify', [PredictController::class, 'identify']);

require __DIR__.'/settings.php';
