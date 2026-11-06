<?php

namespace App\Services;

use App\Jobs\GenerateTenantReport;
use App\Models\Tenant;

class ReportDispatcher
{
    /**
     * Hourly fan-out: one report job per tenant.
     */
    public function dispatchForAllTenants(string $report = 'sales'): void
    {
        foreach (Tenant::query()->orderBy('id')->pluck('id') as $tenantId) {
            GenerateTenantReport::dispatch(Tenant::find($tenantId), $report);
        }
    }

    public function dispatchForTenant(int $tenantId, string $report = 'sales'): void
    {
        GenerateTenantReport::dispatch(Tenant::findOrFail($tenantId), $report);
    }
}
