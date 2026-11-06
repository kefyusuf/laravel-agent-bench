<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/webhooks/payments', PaymentWebhookController::class);
