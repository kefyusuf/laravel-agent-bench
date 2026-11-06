<?php

use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/unsubscribe/{user}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('unsubscribe');
