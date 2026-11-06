<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_includes_each_post_author(): void
    {
        $author = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);
        $other = User::create(['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'password' => 'secret']);

        Post::create(['author_id' => $author->id, 'title' => 'First', 'body' => 'Hello']);
        Post::create(['author_id' => $other->id, 'title' => 'Second', 'body' => 'World']);

        $response = $this->getJson('/posts');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['title' => 'Second', 'author' => 'Grace Hopper']);
    }
}
