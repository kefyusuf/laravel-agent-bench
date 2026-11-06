<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_comment_can_be_approved(): void
    {
        $order = Order::create(['buyer_name' => 'Kim', 'total_cents' => 1000]);
        $comment = $order->comments()->create(['body' => 'Arrived late.']);

        $response = $this->postJson("/admin/comments/{$comment->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'approved' => true]);
    }

    public function test_the_filtered_listing_responds_with_data(): void
    {
        $listing = Listing::create(['title' => 'Vintage camera', 'price_cents' => 5000]);
        $listing->comments()->create(['body' => 'Still available?']);

        $response = $this->getJson('/admin/comments?model=listing');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }
}
