<?php

namespace Tests\Feature;

use App\Models\AuditReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditReport::create(['title' => 'Q3 audit', 'findings' => '3 findings', 'status' => 'draft']);
    }

    public function test_an_admin_user_can_view_and_approve_audit_reports(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@auditpro.test', 'password' => 'password', 'is_admin' => true]);
        $report = AuditReport::first();

        $this->actingAs($admin)->getJson('/admin/audit-reports/' . $report->id)->assertOk();
        $this->actingAs($admin)->patchJson('/admin/audit-reports/' . $report->id . '/approve')->assertOk();
        $this->assertSame('approved', $report->fresh()->status);
    }
}
