<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/checkout', [CheckoutController::class, 'store']);
