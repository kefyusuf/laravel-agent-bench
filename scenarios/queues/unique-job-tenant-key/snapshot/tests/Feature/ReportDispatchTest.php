<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\ReportDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_tenant_dispatch_contains_the_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Initech']);

        Queue::fake();

        (new ReportDispatcher)->dispatchForTenant($tenant->id);

        Queue::assertPushed(\App\Jobs\GenerateTenantReport::class, 1);
    }

    public function test_different_report_types_dispatch_independently(): void
    {
        $tenant = Tenant::create(['name' => 'Acme']);

        Queue::fake();

        (new ReportDispatcher)->dispatchForTenant($tenant->id, 'sales');
        (new ReportDispatcher)->dispatchForTenant($tenant->id, 'revenue');

        Queue::assertPushed(\App\Jobs\GenerateTenantReport::class, 2);
    }
}
