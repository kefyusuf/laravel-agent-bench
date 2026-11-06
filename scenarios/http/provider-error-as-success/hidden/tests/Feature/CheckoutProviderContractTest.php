<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the charge-completion invariant: 'paid' is reachable only from a 2xx
 * provider response that carries a payment reference.
 */
class CheckoutProviderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failing_provider_response_never_marks_the_order_paid(): void
    {
        $order = Order::create(['customer_name' => 'Kim', 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        Http::fake([
            'api.paylane.example/*' => Http::response(['error' => 'upstream timeout', 'reference' => null], 500),
        ]);

        $this->postJson('/checkout', ['order_id' => $order->id]);

        $this->assertSame(
            Order::STATUS_PENDING,
            $order->fresh()->status,
            'A 500 provider response marked the order paid: the HTTP layer succeeded but the charge did not; the response status must gate the transition.',
        );
    }

    public function test_a_2xx_response_without_a_payment_reference_never_marks_the_order_paid(): void
    {
        $order = Order::create(['customer_name' => 'Bo', 'total_cents' => 1200, 'status' => Order::STATUS_PENDING]);

        Http::fake([
            'api.paylane.example/*' => Http::response(['warning' => 'charge accepted but reference unavailable'], 200),
        ]);

        $this->postJson('/checkout', ['order_id' => $order->id]);

        $this->assertSame(
            Order::STATUS_PENDING,
            $order->fresh()->status,
            'A 2xx response without a payment reference marked the order paid; without a reference the charge is unreconcilable and must not complete.',
        );
    }
}
