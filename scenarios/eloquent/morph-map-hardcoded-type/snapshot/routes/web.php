<?php

use App\Http\Controllers\AdminCommentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::get('/admin/comments', [AdminCommentController::class, 'index']);
Route::post('/admin/comments/{comment}/approve', [AdminCommentController::class, 'approve']);
