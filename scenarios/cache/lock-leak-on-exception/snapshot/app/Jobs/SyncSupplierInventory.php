<?php

namespace App\Jobs;

use App\Models\InventorySync;
use App\Models\StockItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncSupplierInventory implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $lock = Cache::lock('inventory-sync', 300);

        if (! $lock->acquire()) {
            // Another sync is in progress; skip this round.
            return;
        }

        $response = Http::timeout(60)->get('https://supplier.example/api/stock');
        $response->throw();

        $items = $response->json('items', []);

        foreach ($items as $item) {
            StockItem::updateOrCreate(
                ['sku' => $item['sku']],
                ['quantity' => (int) $item['quantity']],
            );
        }

        InventorySync::create(['item_count' => count($items)]);

        $lock->release();
    }
}
