<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierInventory;
use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSupplierInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_sync_updates_stock_levels(): void
    {
        Http::fake([
            'supplier.example/*' => Http::response([
                'items' => [
                    ['sku' => 'APL-01', 'quantity' => 42],
                    ['sku' => 'BAN-02', 'quantity' => 7],
                ],
            ], 200),
        ]);

        (new SyncSupplierInventory)->handle();

        $this->assertDatabaseHas('stock_items', ['sku' => 'APL-01', 'quantity' => 42]);
        $this->assertDatabaseCount('inventory_syncs', 1);
    }

    public function test_a_lock_held_by_another_sync_skips_the_run(): void
    {
        \Illuminate\Support\Facades\Cache::lock('inventory-sync', 300)->acquire();

        Http::fake([
            'supplier.example/*' => Http::response(['items' => []], 200),
        ]);

        (new SyncSupplierInventory)->handle();

        $this->assertDatabaseCount('inventory_syncs', 0);
        \Illuminate\Support\Facades\Cache::lock('inventory-sync', 300)->release();
    }
}
