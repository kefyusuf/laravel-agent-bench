<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Encodes the query-efficiency invariant of the highest-traffic endpoint:
 * constant query count, and no lazy loading anywhere on the listing path.
 */
class PostListingEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_never_lazy_loads_relations(): void
    {
        Model::preventLazyLoading(true);

        $authors = collect([
            User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']),
            User::create(['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'password' => 'secret']),
        ]);

        foreach (range(1, 6) as $i) {
            Post::create(['author_id' => $authors[$i % 2]->id, 'title' => "Post {$i}", 'body' => 'Body.']);
        }

        $this->getJson('/posts')->assertOk();
    }

    public function test_the_listing_query_count_is_independent_of_the_post_count(): void
    {
        $author = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret']);

        foreach (range(1, 25) as $i) {
            Post::create(['author_id' => $author->id, 'title' => "Post {$i}", 'body' => 'Body.']);
        }

        DB::connection()->enableQueryLog();

        $this->getJson('/posts')->assertOk();

        $queries = DB::connection()->getQueryLog();

        $this->assertLessThanOrEqual(
            4,
            count($queries),
            'The listing issued '.count($queries)." queries for 25 posts; the query count must not grow with the number of posts (author relation must be eager-loaded).",
        );
    }
}
