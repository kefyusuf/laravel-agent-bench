<?php

namespace Tests\Feature;

use App\Services\FeatureFlagService;
use RedisCluster;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolate tests from each other on the shared cluster.
        $redis = $this->redis();
        foreach (['acme', 'globex'] as $tenant) {
            $redis->del("flags:{$tenant}:uses", "flags:{$tenant}:version", "flags:{$tenant}:enabled");
        }
    }

    public function test_a_flag_can_be_enabled_and_read(): void
    {
        $this->flags()->enable('acme');

        $this->assertSame('1', $this->flags()->get('acme'));
    }

    public function test_usage_is_counted_per_tenant(): void
    {
        $this->flags()->recordUsage('acme');
        $this->flags()->recordUsage('acme');

        $this->assertSame(2, $this->flags()->uses('acme'));
        $this->assertSame(0, $this->flags()->uses('globex'));
    }

    private function flags(): FeatureFlagService
    {
        return new FeatureFlagService;
    }

    private function redis(): RedisCluster
    {
        return new RedisCluster(null, array_map('trim', explode(',', (string) config('database.redis.seeds'))));
    }
}
