<?php

namespace App\Jobs;

use App\Models\LedgerEntry;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SettleSubscriptionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
    }

    public function handle(): void
    {
        // 1. Refund the customer via BillingHub and record it in the ledger.
        $refund = Http::asJson()
            ->withToken((string) config('services.billinghub.key', 'test'))
            ->post('https://api.billinghub.example/v1/refunds', [
                'subscription_id' => $this->subscription->id,
            ]);

        $refund->throw();

        LedgerEntry::create([
            'subscription_id' => $this->subscription->id,
            'kind' => LedgerEntry::KIND_REFUND,
            'amount_cents' => (int) $refund->json('amount_cents'),
        ]);

        // 2. Push the cancellation to the marketplace (known to be flaky).
        $push = Http::asJson()
            ->post('https://api.marketplace.example/v1/cancellations', [
                'subscription_id' => $this->subscription->id,
            ]);

        $push->throw();
    }

    /**
     * Compensate the customer when settlement fails entirely.
     */
    public function failed(): void
    {
        Http::asJson()
            ->withToken((string) config('services.billinghub.key', 'test'))
            ->post('https://api.billinghub.example/v1/refunds', [
                'subscription_id' => $this->subscription->id,
            ]);

        LedgerEntry::create([
            'subscription_id' => $this->subscription->id,
            'kind' => LedgerEntry::KIND_REFUND,
            'amount_cents' => 9900,
        ]);
    }
}
