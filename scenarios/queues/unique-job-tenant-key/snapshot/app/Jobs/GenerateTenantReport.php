<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTenantReport implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $report = 'sales',
    ) {
    }

    public function uniqueId(): string
    {
        return $this->report.'-report';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        // Generates and stores the tenant's report snapshot.
        $this->tenant->forceFill([
            'last_report_generated_at' => now(),
        ])->save();
    }
}
