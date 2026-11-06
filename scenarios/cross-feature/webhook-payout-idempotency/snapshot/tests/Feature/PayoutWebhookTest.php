<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayout;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payrail.webhook_secret', 'test-secret');

        Http::fake([
            'api.payrail.example/*' => Http::response(['reference' => 'pr_123'], 200),
        ]);
    }

    public function test_a_valid_webhook_marks_the_order_paid_and_schedules_the_payout(): void
    {
        $order = $this->pendingOrder();

        Queue::fake();

        [$payload, $headers] = $this->signedWebhook('evt_100', $order->id);

        $response = $this->postJson('/webhooks/payments', $payload, $headers);

        $response->assertOk()->assertJson(['message' => 'accepted']);

        $this->assertDatabaseHas('payment_events', ['provider_event_id' => 'evt_100']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        Queue::assertPushed(ProcessPayout::class, 1);
    }

    public function test_a_duplicate_webhook_delivery_does_not_duplicate_the_event(): void
    {
        $order = $this->pendingOrder();

        Queue::fake();

        [$payload, $headers] = $this->signedWebhook('evt_200', $order->id);

        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();
        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();

        $this->assertDatabaseCount('payment_events', 1);
        Queue::assertPushed(ProcessPayout::class, 1);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $order = $this->pendingOrder();

        Queue::fake();

        $response = $this->postJson('/webhooks/payments', [
            'id' => 'evt_300',
            'type' => 'payment.succeeded',
            'data' => ['order_id' => $order->id],
        ], ['X-PayRail-Signature' => 'deadbeef']);

        $response->assertStatus(401);

        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        Queue::assertNothingPushed();
    }

    private function pendingOrder(): Order
    {
        return Order::create([
            'seller_id' => 1,
            'total_cents' => 4250,
            'status' => 'pending',
        ]);
    }

    /** @return array{0: array, 1: array<string, string>} payload + signed headers */
    private function signedWebhook(string $eventId, int $orderId): array
    {
        $payload = [
            'id' => $eventId,
            'type' => 'payment.succeeded',
            'data' => ['order_id' => $orderId],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), (string) config('services.payrail.webhook_secret'));

        return [$payload, ['X-PayRail-Signature' => $signature]];
    }
}
