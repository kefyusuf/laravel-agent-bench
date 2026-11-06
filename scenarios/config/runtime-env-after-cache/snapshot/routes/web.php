<?php

use App\Http\Controllers\ChargeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/charge', ChargeController::class);
