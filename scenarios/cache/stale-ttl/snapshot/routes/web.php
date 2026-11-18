<?php

use App\Http\Controllers\RateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/rates/{from}/{to}', [RateController::class, 'show']);
