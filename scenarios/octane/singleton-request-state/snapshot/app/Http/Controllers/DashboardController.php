<?php

namespace App\Http\Controllers;

use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context): JsonResponse
    {
        return response()->json([
            'tenant' => $context->tenant->name,
            'orders' => $context->tenant->orders()->count(),
        ]);
    }
}
