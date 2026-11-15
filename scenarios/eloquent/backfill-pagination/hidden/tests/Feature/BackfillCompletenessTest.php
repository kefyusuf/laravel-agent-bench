<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the self-mutating-pagination invariant: with 250 rows and chunk
 * size 100, the loop body stamps the very column the WHERE filters on — every
 * batch boundary skips the rows that shifted out of the filter. Exactly 250
 * rows must be stamped.
 */
class BackfillCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_un_indexed_order_is_stamped_exactly_once(): void
    {
        for ($i = 1; $i <= 250; $i++) {
            Order::create(['reference' => "R-{$i}", 'backfilled_at' => null]);
        }

        $this->artisan('orders:backfill-index')->assertSuccessful();

        $this->assertSame(
            250,
            Order::whereNotNull('backfilled_at')->count(),
            'The backfill stamped fewer rows than it reported: chunk() re-evaluates the WHERE clause while the loop stamps the filtered column, skipping rows at every batch boundary. Pagination over a self-mutating filter must key by stable identifiers.',
        );

        $this->assertSame(0, Order::whereNull('backfilled_at')->count());
    }
}
