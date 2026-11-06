<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.paylane.example/*' => Http::response(['reference' => 'pl_9001'], 200),
        ]);
    }

    public function test_a_charge_marks_the_order_paid_and_records_the_charge(): void
    {
        $order = Order::create(['customer_id' => 7, 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        $response = $this->postJson('/checkout', ['order_id' => $order->id]);

        $response->assertOk();
        $response->assertJsonPath('reference', 'pl_9001');

        $this->assertDatabaseHas('charges', [
            'order_id' => $order->id,
            'customer_id' => 7,
            'reference' => 'pl_9001',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PAID]);
    }

    public function test_charges_are_recorded_per_order(): void
    {
        $orderA = Order::create(['customer_id' => 7, 'total_cents' => 1000, 'status' => Order::STATUS_PENDING]);
        $orderB = Order::create(['customer_id' => 8, 'total_cents' => 2000, 'status' => Order::STATUS_PENDING]);

        $this->postJson('/checkout', ['order_id' => $orderA->id])->assertOk();
        $this->postJson('/checkout', ['order_id' => $orderB->id])->assertOk();

        $this->assertSame(2, Charge::count());
    }
}
