<?php

use App\Http\Controllers\DigestController;
use App\Http\Controllers\DigestSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/digest/settings', [DigestSettingsController::class, 'update']);
Route::post('/digest/{customer}/send', [DigestController::class, 'send'])->middleware('throttle:digest');
