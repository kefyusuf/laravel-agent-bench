<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::patch('/profile', [ProfileController::class, 'update'])->middleware('auth');
