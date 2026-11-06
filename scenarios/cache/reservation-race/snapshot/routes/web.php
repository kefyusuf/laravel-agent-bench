<?php

use App\Http\Controllers\FlashSaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/flashsale/{sku}/reserve', [FlashSaleController::class, 'reserve']);
