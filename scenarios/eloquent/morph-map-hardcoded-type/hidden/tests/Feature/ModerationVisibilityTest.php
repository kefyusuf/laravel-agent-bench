<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Encodes the polymorphic-storage invariant: with the morph map enforced,
 * commentable_type stores aliases ('order'), raw queries must filter by
 * getMorphClass(), and historic aliased rows must stay readable via relations.
 */
class ModerationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_order_comment_view_shows_comments_left_on_orders(): void
    {
        $order = Order::create(['buyer_name' => 'Kim', 'total_cents' => 1000]);
        $order->comments()->create(['body' => 'Arrived late but in perfect shape.']);

        $response = $this->getJson('/admin/comments?model=order');

        $response->assertOk();
        $this->assertCount(
            1,
            $response->json('data'),
            'The moderation view must show comments left on orders; if it is empty, the filter is comparing against the wrong polymorphic type representation.',
        );
        $this->assertSame('Arrived late but in perfect shape.', $response->json('data.0.body'));
    }

    public function test_historic_comments_stored_with_morph_aliases_stay_readable(): void
    {
        $order = Order::create(['buyer_name' => 'Bo', 'total_cents' => 2500]);

        // Historic row written before the current code existed, storing the alias.
        DB::table('comments')->insert([
            'commentable_type' => 'order',
            'commentable_id' => $order->id,
            'body' => 'Legacy comment',
            'approved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $order->comments()->where('body', 'Legacy comment')->exists(),
            'Comments persisted with the morph-map alias must remain accessible through the relation; removing the morph map orphans all historic rows.',
        );
    }
}
