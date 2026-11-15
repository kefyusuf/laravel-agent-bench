<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_small_backfill_stamps_every_order(): void
    {
        // Small enough to fit in ONE chunk — exercises the happy path only.
        for ($i = 1; $i <= 50; $i++) {
            Order::create(['reference' => "R-{$i}", 'backfilled_at' => null]);
        }

        $this->artisan('orders:backfill-index')->assertSuccessful();

        $this->assertSame(50, Order::whereNotNull('backfilled_at')->count());
    }

    public function test_a_rerun_processes_nothing_new(): void
    {
        Order::create(['reference' => 'R-1', 'backfilled_at' => null]);

        $this->artisan('orders:backfill-index')->assertSuccessful();
        $this->artisan('orders:backfill-index')->assertSuccessful();

        $this->assertSame(1, Order::whereNotNull('backfilled_at')->count());
    }
}
