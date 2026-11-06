<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_charge_marks_the_order_paid_with_the_reference(): void
    {
        $order = Order::create(['customer_name' => 'Kim', 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        Http::fake([
            'api.paylane.example/*' => Http::response(['reference' => 'pl_9001'], 200),
        ]);

        $response = $this->postJson('/checkout', ['order_id' => $order->id]);

        $response->assertOk();
        $response->assertJsonPath('reference', 'pl_9001');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PAID,
            'provider_reference' => 'pl_9001',
        ]);
    }
}
