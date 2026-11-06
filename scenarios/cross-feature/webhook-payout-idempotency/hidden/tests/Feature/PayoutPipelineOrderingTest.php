<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * These checks encode the queue-ordering invariant of the payout pipeline.
 * They use the database queue connection so that enqueue behaviour is observable.
 */
class PayoutPipelineOrderingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payrail.webhook_secret', 'test-secret');

        Http::fake([
            'api.payrail.example/*' => Http::response(['reference' => 'pr_123'], 200),
        ]);
    }

    public function test_no_payout_job_is_enqueued_before_the_webhook_transaction_commits(): void
    {
        $order = Order::create([
            'seller_id' => 1,
            'total_cents' => 4250,
            'status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_order_1',
            'type' => 'payment.succeeded',
            'data' => ['order_id' => $order->id],
        ];
        $headers = ['X-PayRail-Signature' => hash_hmac('sha256', json_encode($payload), 'test-secret')];

        DB::beginTransaction();

        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();

        $this->assertSame(
            0,
            DB::table('jobs')->count(),
            'The payout job entered the queue before the transaction committed: the dispatch is not deferred to afterCommit, so the worker can process (and persist) work whose backing event may never commit.',
        );

        DB::commit();

        $this->assertSame(1, DB::table('jobs')->count(), 'After the commit the payout job must exist exactly once.');
    }

    public function test_a_rolled_back_webhook_transaction_leaves_no_payout_job(): void
    {
        $order = Order::create([
            'seller_id' => 1,
            'total_cents' => 4250,
            'status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_order_2',
            'type' => 'payment.succeeded',
            'data' => ['order_id' => $order->id],
        ];
        $headers = ['X-PayRail-Signature' => hash_hmac('sha256', json_encode($payload), 'test-secret')];

        DB::beginTransaction();

        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();

        DB::rollBack();

        $this->assertSame(0, DB::table('jobs')->count(), 'A rolled-back webhook must leave no payout job behind.');
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_duplicate_deliveries_produce_exactly_one_payout_job(): void
    {
        $order = Order::create([
            'seller_id' => 1,
            'total_cents' => 4250,
            'status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_order_3',
            'type' => 'payment.succeeded',
            'data' => ['order_id' => $order->id],
        ];
        $headers = ['X-PayRail-Signature' => hash_hmac('sha256', json_encode($payload), 'test-secret')];

        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();
        $this->postJson('/webhooks/payments', $payload, $headers)->assertOk();

        $this->assertDatabaseCount('payment_events', 1);
        $this->assertSame(1, DB::table('jobs')->count(), 'Duplicate deliveries must still produce at most one payout job.');
    }
}
