<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the idempotency-key feature spec: replay keyed by (key, customer),
 * only successful charges stored, no double charging.
 * Each test declares its own Http::fake — fake calls do not stack.
 */
class IdempotencySpecTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retry_with_the_same_key_replays_and_does_not_charge_twice(): void
    {
        Http::fake([
            'api.paylane.example/*' => Http::response(['reference' => 'pl_9001'], 200),
        ]);

        $order = Order::create(['customer_id' => 7, 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        $first = $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-abc-1']);
        $first->assertOk();
        $second = $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-abc-1']);
        $second->assertOk();

        $this->assertSame(
            $first->getContent(),
            $second->getContent(),
            'The retry with the same Idempotency-Key must return the stored response body byte-for-byte.',
        );

        $this->assertSame(
            1,
            Charge::where('order_id', $order->id)->count(),
            'A retried charge request created a second charge: the provider was called again despite the idempotency key.',
        );
    }

    public function test_a_different_key_charges_again(): void
    {
        Http::fake([
            'api.paylane.example/*' => Http::response(['reference' => 'pl_9001'], 200),
        ]);

        $order = Order::create(['customer_id' => 7, 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-abc-1'])->assertOk();
        $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-abc-2'])->assertOk();

        $this->assertSame(2, Charge::where('order_id', $order->id)->count(), 'A different key is a different charge.');
    }

    public function test_the_same_key_from_a_different_customer_is_a_different_operation(): void
    {
        Http::fake([
            'api.paylane.example/*' => Http::response(['reference' => 'pl_9001'], 200),
        ]);

        $orderA = Order::create(['customer_id' => 7, 'total_cents' => 1000, 'status' => Order::STATUS_PENDING]);
        $orderB = Order::create(['customer_id' => 8, 'total_cents' => 1000, 'status' => Order::STATUS_PENDING]);

        $this->postJson('/checkout', ['order_id' => $orderA->id], ['Idempotency-Key' => 'shared-key'])->assertOk();
        $response = $this->postJson('/checkout', ['order_id' => $orderB->id], ['Idempotency-Key' => 'shared-key']);

        $response->assertOk();

        $this->assertSame(
            $orderB->id,
            $response->json('order.id'),
            'The same key from a different customer replayed customer A\'s charge response: keys must be scoped per customer.',
        );

        $this->assertSame(2, Charge::count(), 'Both customers must be charged.');
    }

    public function test_a_failed_charge_is_not_stored_so_a_retry_can_run(): void
    {
        $order = Order::create(['customer_id' => 7, 'total_cents' => 5000, 'status' => Order::STATUS_PENDING]);

        // First attempt fails, the retry succeeds — one fake, one sequence.
        Http::fake([
            'api.paylane.example/*' => Http::sequence()
                ->push(['error' => 'gateway timeout'], 500)
                ->push(['reference' => 'pl_retry'], 200),
        ]);
        $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-fail-1']);

        $response = $this->postJson('/checkout', ['order_id' => $order->id], ['Idempotency-Key' => 'mob-fail-1']);

        $response->assertOk();

        $this->assertSame(1, Charge::where('order_id', $order->id)->count(), 'A retry after a failed provider call must perform a fresh charge.');
        $this->assertSame('pl_retry', $response->json('reference'));
    }
}
