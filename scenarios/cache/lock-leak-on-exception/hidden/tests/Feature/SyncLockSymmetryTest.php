<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the lock-symmetry invariant of the inventory sync job.
 * The array cache store keeps locks alive across handle() calls in one process,
 * which is exactly what a leaked lock does in production between worker runs.
 */
class SyncLockSymmetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_supplier_call_does_not_block_the_next_sync(): void
    {
        Http::fake([
            'supplier.example/*' => Http::sequence()
                ->push(['error' => 'upstream outage'], 500)
                ->push(['items' => [['sku' => 'APL-01', 'quantity' => 9]]], 200),
        ]);

        try {
            (new SyncSupplierInventory)->handle();
            $this->fail('The first sync must surface the supplier failure.');
        } catch (RequestException $e) {
            // expected: the failure propagates to the queue's retry machinery
        }

        // The supplier API has recovered; the very next sync must succeed.
        (new SyncSupplierInventory)->handle();

        $this->assertDatabaseHas('stock_items', ['sku' => 'APL-01', 'quantity' => 9]);
        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('inventory_syncs')->count(),
            'After a supplier outage the next sync must actually run; a leaked lock makes every later run silently skip.',
        );
    }

    public function test_a_failed_sync_is_not_recorded_as_completed(): void
    {
        Http::fake([
            'supplier.example/*' => Http::response(['error' => 'upstream outage'], 500),
        ]);

        try {
            (new SyncSupplierInventory)->handle();
            $this->fail('The failing sync must not be swallowed.');
        } catch (RequestException $e) {
            // expected
        }

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('inventory_syncs')->count(), 'A failed supplier call must never be recorded as a completed sync.');
    }
}
