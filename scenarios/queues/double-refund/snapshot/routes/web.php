<?php

use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
