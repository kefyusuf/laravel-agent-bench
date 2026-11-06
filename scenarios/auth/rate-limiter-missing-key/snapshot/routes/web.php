<?php

use App\Http\Controllers\OtpRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/request-otp', OtpRequestController::class)->middleware('throttle:otp');
