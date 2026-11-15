<?php

namespace Tests\Feature;

use App\Models\MarketplaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.marketplace.example/*' => Http::response([
                'orders' => [
                    ['id' => 'MO-1', 'total_cents' => 4200],
                    ['id' => 'MO-2', 'total_cents' => 1500],
                ],
            ], 200),
        ]);
    }

    public function test_the_sync_imports_marketplace_orders(): void
    {
        $this->artisan('app:sync-marketplace')->assertSuccessful();

        $this->assertDatabaseHas('marketplace_orders', ['external_id' => 'MO-1', 'total_cents' => 4200]);
        $this->assertDatabaseHas('marketplace_orders', ['external_id' => 'MO-2', 'total_cents' => 1500]);
    }

    public function test_repeated_syncs_do_not_duplicate_orders(): void
    {
        $this->artisan('app:sync-marketplace')->assertSuccessful();
        $this->artisan('app:sync-marketplace')->assertSuccessful();

        $this->assertSame(2, MarketplaceOrder::count());
    }
}
