<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid']);
