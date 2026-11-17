<?php

use App\Http\Controllers\CustomerTaxIdController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/customers/{customer}/tax-id', [CustomerTaxIdController::class, 'show']);
Route::patch('/customers/{customer}/tax-id', [CustomerTaxIdController::class, 'update']);
