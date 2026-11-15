<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncMarketplaceCommand extends Command
{
    /**
     * The marketplace sync legitimately runs up to this long during peak
     * windows (documented by ops — the scheduling of this command must never
     * assume a shorter run).
     */
    public const MAX_RUNTIME_MINUTES = 45;

    protected $signature = 'app:sync-marketplace';

    protected $description = 'Sync new orders from the marketplace into the local store';

    public function handle(): int
    {
        $response = Http::asJson()
            ->withToken((string) config('services.marketplace.key', 'test'))
            ->get('https://api.marketplace.example/v1/orders', ['since' => now()->subHour()->toIso8601String()]);

        $response->throw();

        foreach ((array) $response->json('orders') as $order) {
            MarketplaceOrder::updateOrCreate(
                ['external_id' => $order['id']],
                ['total_cents' => (int) $order['total_cents']],
            );
        }

        $this->info('sync complete: ' . count((array) $response->json('orders')) . ' orders');

        return self::SUCCESS;
    }
}
