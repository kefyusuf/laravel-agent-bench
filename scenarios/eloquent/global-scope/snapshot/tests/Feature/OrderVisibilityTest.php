<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderVisibilityTest extends TestCase
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

    public function test_customer_facing_order_list_hides_archived_orders(): void
    {
        $response = $this->getJson('/orders');

        $response->assertOk();
        $refs = collect($response->json('orders'))->pluck('reference');
        $this->assertContains('R-LIVE-1', $refs);
        $this->assertNotContains('R-ARCH-1', $refs);
    }
}
