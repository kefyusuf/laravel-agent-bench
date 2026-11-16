<?php

use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::post('/contracts', [ContractController::class, 'store']);
Route::get('/contracts/{contract}/link', [ContractController::class, 'link']);
Route::get('/download/{contract}', [ContractController::class, 'download'])
    ->name('contracts.download')
    ->middleware('signed');
