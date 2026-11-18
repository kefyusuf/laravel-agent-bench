<?php

use App\Http\Controllers\AuditReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent());

Route::middleware('auth')->group(function () {
    Route::get('/admin/audit-reports/{report}', [AuditReportController::class, 'show']);
    Route::patch('/admin/audit-reports/{report}/approve', [AuditReportController::class, 'approve']);
});
