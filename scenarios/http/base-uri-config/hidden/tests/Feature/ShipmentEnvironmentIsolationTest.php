<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the environment-isolation invariant: the provider base URI must be
 * resolved from config at call time — changing services.shipfast.base_uri
 * changes the destination of every request with no code change.
 */
class ShipmentEnvironmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_follow_the_configured_base_uri(): void
    {
        config(['services.shipfast.base_uri' => 'https://sandbox.shipfast.example']);

        Http::fake([
            'https://sandbox.shipfast.example/*' => Http::response(['reference' => 'SF-SBX'], 200),
            'https://api.shipfast.example/*' => Http::response(['reference' => 'SF-PROD'], 200),
        ]);

        $this->postJson('/shipments', ['order_id' => 'ORD-SBX', 'destination' => 'Rotterdam'])->assertCreated();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://sandbox.shipfast.example/');
        });
    }

    public function test_changing_the_config_changes_the_destination(): void
    {
        config(['services.shipfast.base_uri' => 'https://staging.shipfast.example']);

        Http::fake([
            'https://staging.shipfast.example/*' => Http::response(['reference' => 'SF-STG'], 200),
        ]);

        $this->postJson('/shipments', ['order_id' => 'ORD-STG', 'destination' => 'Berlin'])->assertCreated();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://staging.shipfast.example/');
        });
    }
}
