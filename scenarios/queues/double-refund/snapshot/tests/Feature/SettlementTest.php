<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.billinghub.example/*' => Http::response(['amount_cents' => 9900], 200),
            'api.marketplace.example/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_cancelling_a_subscription_settles_and_refunds_once(): void
    {
        $subscription = Subscription::create(['customer_name' => 'Kim', 'status' => 'active']);

        $this->postJson("/subscriptions/{$subscription->id}/cancel")->assertOk();

        $this->assertSame(1, LedgerEntry::where('subscription_id', $subscription->id)
            ->where('kind', LedgerEntry::KIND_REFUND)->count());
    }
}
