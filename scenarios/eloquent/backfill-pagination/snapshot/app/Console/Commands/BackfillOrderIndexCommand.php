<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class BackfillOrderIndexCommand extends Command
{
    protected $signature = 'orders:backfill-index';

    protected $description = 'Stamp every un-indexed order as backfilled (the indexer picks them up)';

    public function handle(): int
    {
        $processed = 0;

        Order::query()
            ->whereNull('backfilled_at')
            ->chunk(100, function ($orders) use (&$processed) {
                foreach ($orders as $order) {
                    $order->forceFill(['backfilled_at' => now()])->save();
                    $processed++;
                }
            });

        $this->info("backfilled {$processed} orders");

        return self::SUCCESS;
    }
}
