<?php

use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::middleware('auth')->group(function () {
    Route::get('/customers/{customer}/invoices/{invoice}', [CustomerInvoiceController::class, 'show']);
});
