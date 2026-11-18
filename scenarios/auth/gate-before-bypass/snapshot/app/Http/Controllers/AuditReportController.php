<?php

namespace App\Http\Controllers;

use App\Models\AuditReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditReportController extends Controller
{
    public function show(AuditReport $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json(['report' => ['title' => $report->title, 'findings' => $report->findings]]);
    }

    public function approve(Request $request, AuditReport $report): JsonResponse
    {
        $this->authorize('approve', $report);

        $report->forceFill(['status' => 'approved'])->save();

        return response()->json(['report' => ['id' => $report->id, 'status' => 'approved']]);
    }
}
