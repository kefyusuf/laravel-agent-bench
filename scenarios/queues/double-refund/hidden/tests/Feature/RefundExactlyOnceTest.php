<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the exactly-once refund invariant across BOTH failure timings:
 * an exception after the refund (the flaky marketplace push) must not trigger
 * a second refund in failed(), and an exception before the refund must still
 * be compensated by failed().
 */
class RefundExactlyOnceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failure_after_the_refund_does_not_refund_twice(): void
    {
        $subscription = Subscription::create(['customer_name' => 'Kim', 'status' => 'active']);

        Http::fake([
            'api.billinghub.example/*' => Http::response(['amount_cents' => 9900], 200),
            // The flaky marketplace push fails AFTER the refund succeeded.
            'api.marketplace.example/*' => Http::response(['error' => 'marketplace unavailable'], 500),
        ]);

        $this->postJson("/subscriptions/{$subscription->id}/cancel");

        $this->assertSame(
            1,
            LedgerEntry::where('subscription_id', $subscription->id)
                ->where('kind', LedgerEntry::KIND_REFUND)->count(),
            'The customer was refunded twice for one cancellation: the refund already happened, and the failure of the LATER step must not trigger the compensation hook again.',
        );
    }

    public function test_a_failure_before_the_refund_is_still_compensated(): void
    {
        $subscription = Subscription::create(['customer_name' => 'Bo', 'status' => 'active']);

        Http::fake([
            // BillingHub itself is down: the refund never happened.
            'api.billinghub.example/*' => Http::response(['error' => 'billing api down'], 500),
            'api.marketplace.example/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson("/subscriptions/{$subscription->id}/cancel");

        $this->assertSame(
            1,
            LedgerEntry::where('subscription_id', $subscription->id)
                ->where('kind', LedgerEntry::KIND_REFUND)->count(),
            'A genuine settlement failure before the refund was not compensated: failed() must still refund exactly once when the refund never happened.',
        );
    }
}
