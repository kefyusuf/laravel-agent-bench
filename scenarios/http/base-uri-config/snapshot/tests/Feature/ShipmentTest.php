<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*shipfast.example/*' => Http::response(['reference' => 'SF-1001'], 200),
        ]);
    }

    public function test_a_shipment_is_created_and_recorded(): void
    {
        $response = $this->postJson('/shipments', [
            'order_id' => 'ORD-9',
            'destination' => 'Rotterdam',
        ]);

        $response->assertCreated();
        $response->assertJson(['shipment' => ['reference' => 'SF-1001']]);

        $this->assertDatabaseHas('shipments', [
            'order_id' => 'ORD-9',
            'provider_reference' => 'SF-1001',
            'destination' => 'Rotterdam',
        ]);
    }
}
