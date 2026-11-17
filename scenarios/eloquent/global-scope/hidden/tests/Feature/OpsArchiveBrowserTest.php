<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the dual-visibility invariant: the global scope keeps archived
 * orders out of customer-facing surfaces while the ops archive browser sees
 * ALL orders — both on the same model.
 */
class OpsArchiveBrowserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::create(['reference' => 'R-LIVE-1']);
        Order::create(['reference' => 'R-LIVE-2']);
        Order::create(['reference' => 'R-ARCH-1', 'archived_at' => now()]);
        Order::create(['reference' => 'R-ARCH-2', 'archived_at' => now()]);
    }

    public function test_the_ops_archive_browser_shows_every_order(): void
    {
        $response = $this->getJson('/ops/orders');

        $response->assertOk();

        $refs = collect($response->json('orders'))->pluck('reference');

        $this->assertContains(
            'R-ARCH-1',
            $refs,
            'The ops archive browser hides archived orders: the global scope silently applied to the ops query. The archive browser must opt out with withoutGlobalScope.',
        );
        $this->assertContains('R-LIVE-1', $refs);
        $this->assertSame(4, $refs->count(), 'The ops browser must see all 4 orders (2 live + 2 archived).');
    }

    public function test_customer_facing_surfaces_still_hide_archived_orders(): void
    {
        $response = $this->getJson('/orders');

        $response->assertOk();

        $refs = collect($response->json('orders'))->pluck('reference');

        $this->assertNotContains(
            'R-ARCH-1',
            $refs,
            'Archived orders leaked into the customer-facing order list: the fix must not remove the global scope from the model.',
        );
    }
}
