<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\OpsOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/ops/orders', [OpsOrderController::class, 'index']);
