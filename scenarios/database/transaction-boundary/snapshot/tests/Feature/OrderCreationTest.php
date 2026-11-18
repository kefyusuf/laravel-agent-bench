<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.dockreserve.example/*' => Http::response(['slot_id' => 'SL-9'], 200),
        ]);
    }

    public function test_an_order_is_created_with_a_provider_reservation(): void
    {
        $response = $this->postJson('/orders', [
            'reference' => 'ORD-1',
            'destination' => 'Rotterdam',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['reference' => 'ORD-1', 'status' => 'reserved']);
    }
}
