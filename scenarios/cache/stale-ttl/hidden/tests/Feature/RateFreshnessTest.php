<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the freshness window: a cached rate older than 10 minutes must be
 * re-fetched from the provider; a rate fetched within the window must be
 * served from cache without hitting the provider again. Time travel makes
 * the window deterministic.
 */
class RateFreshnessTest extends TestCase
{
    public function test_rates_older_than_the_window_are_refetched(): void
    {
        Carbon::setTestNow('2026-03-01 09:00:00');

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['rate' => 1.08 + $calls * 0.01], 200);
        });

        $first = $this->getJson('/rates/USD/EUR');
        $first->assertOk();
        $this->assertSame(1, $calls, 'The first quote must hit the provider.');

        Carbon::setTestNow('2026-03-01 09:11:00'); // 11 minutes later

        $second = $this->getJson('/rates/USD/EUR');
        $second->assertOk();

        $this->assertSame(
            2,
            $calls,
            'A rate cached 11 minutes ago was served again: the freshness window is broken — pricing uses stale rates indefinitely.',
        );
    }

    public function test_rates_within_the_window_are_served_from_cache(): void
    {
        Carbon::setTestNow('2026-03-01 09:00:00');

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['rate' => 1.08], 200);
        });

        $this->getJson('/rates/USD/EUR')->assertOk();

        Carbon::setTestNow('2026-03-01 09:05:00'); // 5 minutes later — still fresh

        $this->getJson('/rates/USD/EUR')->assertOk();

        $this->assertSame(
            1,
            $calls,
            'A rate fetched 5 minutes ago was re-fetched: the cache must shield the provider within the freshness window.',
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
