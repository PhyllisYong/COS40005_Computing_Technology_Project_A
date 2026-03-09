<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'dashboard')->name('home');
Route::inertia('/identification', 'identification')->name('identification');
Route::inertia('/digitalisation', 'digitalisation')->name('digitalisation');
Route::inertia('/digitalisation1', 'digitalisation1')->name('digitalisation1');
require __DIR__.'/settings.php';
