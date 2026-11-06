<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/posts', [PostController::class, 'index']);
