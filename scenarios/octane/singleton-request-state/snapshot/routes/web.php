<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/dashboard', DashboardController::class);
