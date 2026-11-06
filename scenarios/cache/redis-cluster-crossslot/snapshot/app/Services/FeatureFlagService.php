<?php

namespace App\Services;

use Redis;
use RedisCluster;

class FeatureFlagService
{
    private RedisCluster $redis;

    public function __construct()
    {
        $seeds = array_map('trim', explode(',', (string) config('database.redis.seeds')));

        $this->redis = new RedisCluster(null, $seeds);
    }

    public function get(string $tenant): string
    {
        return (string) $this->redis->get("flags:{$tenant}:enabled");
    }

    public function enable(string $tenant): void
    {
        $this->redis->set("flags:{$tenant}:enabled", '1');
    }

    public function uses(string $tenant): int
    {
        return (int) $this->redis->get("flags:{$tenant}:uses");
    }

    public function recordUsage(string $tenant): int
    {
        return (int) $this->redis->incr("flags:{$tenant}:uses");
    }

    /**
     * Releases version $version for the tenant: the usage counter and the
     * version marker must move together atomically.
     */
    public function releaseVersion(string $tenant, int $version): void
    {
        $script = <<<'LUA'
            redis.call('INCR', KEYS[1])
            redis.call('SET', KEYS[2], ARGV[1])
            LUA;

        $this->redis->eval($script, [
            "flags:{$tenant}:uses",
            "flags:{$tenant}:version",
            $version,
        ], 2);
    }
}
