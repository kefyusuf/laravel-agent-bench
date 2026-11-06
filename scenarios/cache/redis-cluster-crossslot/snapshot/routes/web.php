<?php

use App\Http\Controllers\FeatureFlagController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/flags/{tenant}', [FeatureFlagController::class, 'show']);
Route::post('/flags/{tenant}/release', [FeatureFlagController::class, 'release']);
