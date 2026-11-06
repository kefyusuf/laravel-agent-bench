<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_requested_tenant(): void
    {
        $acme = Tenant::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $acme->orders()->createMany([['reference' => 'A-1'], ['reference' => 'A-2']]);

        $response = $this->withHeaders(['X-Tenant' => 'acme'])->getJson('/dashboard');

        $response->assertOk();
        $this->assertSame('Acme Corp', $response->json('tenant'));
        $this->assertSame(2, $response->json('orders'));
    }

    public function test_an_unknown_tenant_is_rejected(): void
    {
        $this->getJson('/dashboard')->assertStatus(404);
    }
}
