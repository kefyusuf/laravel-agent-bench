<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\ReportDispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Encodes the uniqueness invariant against the real (unfaked) database queue:
 * with no worker running, queued jobs stay in the jobs table and the
 * ShouldBeUnique lock stays held, exactly like a production backlog.
 */
class ReportUniquenessTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_fan_out_enqueues_one_report_per_tenant(): void
    {
        Tenant::create(['name' => 'Acme']);
        Tenant::create(['name' => 'Globex']);
        Tenant::create(['name' => 'Initech']);

        (new ReportDispatcher)->dispatchForAllTenants();

        $this->assertSame(
            3,
            DB::table('jobs')->count(),
            'A tenant whose report job was dropped by the uniqueness filter will report missing reports: one in-flight (or queued) report per tenant requires the uniqueness key to include the tenant identity.',
        );
    }

    public function test_the_same_tenant_cannot_have_two_queued_reports_of_the_same_type(): void
    {
        $tenant = Tenant::create(['name' => 'Acme']);

        (new ReportDispatcher)->dispatchForTenant($tenant->id);
        (new ReportDispatcher)->dispatchForTenant($tenant->id);

        $this->assertSame(1, DB::table('jobs')->count(), 'Overlapping report generation for one tenant must remain impossible.');
    }
}
