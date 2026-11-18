<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the system-agreement invariant: the provider and the local database
 * must agree for the same flow. A provider reservation may never exist without
 * a corresponding local order — and the local order must never claim a
 * reservation the provider did not grant.
 */
class ProviderAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_provider_reservation_implies_a_local_order(): void
    {
        // The provider accepts the reservation but the response is missing the
        // slot id — the flow fails AFTER the provider call. The provider call
        // cannot be rolled back, so the local order must already have been
        // committed when the provider was asked.
        Http::fake([
            'api.dockreserve.example/*' => Http::response(['accepted' => true], 200),
        ]);

        $this->postJson('/orders', [
            'reference' => 'ORD-DRIFT',
            'destination' => 'Rotterdam',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'dockreserve');
        });

        $this->assertDatabaseHas('orders', ['reference' => 'ORD-DRIFT', 'status' => 'reserved']);
    }

    public function test_a_provider_failure_never_leaves_a_locally_reserved_order(): void
    {
        // The provider rejected the reservation. The local order may survive
        // (it commits before the provider call) but it must not claim 'reserved':
        // that would be the mirror-image drift — a local order for a slot the
        // provider never granted.
        Http::fake([
            'api.dockreserve.example/*' => Http::response(['error' => 'dock full'], 500),
        ]);

        $this->postJson('/orders', [
            'reference' => 'ORD-FAIL',
            'destination' => 'Rotterdam',
        ]);

        $this->assertDatabaseHas('orders', ['reference' => 'ORD-FAIL', 'status' => 'pending']);
    }
}
