<?php

namespace Tests\Feature;

use App\Models\AuditReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the role-boundary invariant: admin users bypass every policy check
 * (Gate::before), while staff users without the admin role are evaluated by
 * the normal policies — which deny them. BOTH must hold on the same model.
 */
class GateRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditReport::create(['title' => 'Q3 audit', 'findings' => '3 findings', 'status' => 'draft']);
    }

    public function test_an_admin_bypasses_every_policy_check(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@auditpro.test', 'password' => 'password', 'is_admin' => true]);
        $report = AuditReport::first();

        $this->actingAs($admin)->getJson('/admin/audit-reports/' . $report->id)->assertOk();
    }

    public function test_a_staff_user_is_denied_by_the_normal_policies(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@auditpro.test', 'password' => 'password', 'is_admin' => false]);
        $report = AuditReport::first();

        $response = $this->actingAs($staff)->getJson('/admin/audit-reports/' . $report->id);

        $response->assertStatus(
            403,
            'A staff user opened an admin audit report: the role boundary must hold — admins bypass policies, staff are denied by them.',
        );
    }

    public function test_an_unauthenticated_user_is_denied(): void
    {
        $report = AuditReport::first();

        $this->getJson('/admin/audit-reports/' . $report->id)->assertStatus(401);
    }
}
