<?php

namespace Tests\Feature;

use RedisCluster;
use Tests\TestCase;

/**
 * Encodes the cluster-safety invariant of the version release: the two keys of
 * the atomic Lua bump must share a cluster slot (hash tag), the release must
 * stay a single atomic server-side operation, and tenants must stay isolated.
 * Runs against the real 3-master cluster.
 *
 * Note: the release contract fixes the canonical key names at
 * flags:{<tenant>}:uses and flags:{<tenant>}:version — the braces are part of
 * the key name (they are the hash tag that co-locates the pair).
 */
class ClusterSlotSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The visible suite writes un-braced keys on the shared cluster in the
        // same process; clean both representations before each test.
        $redis = $this->redis();
        foreach (['acme', 'globex'] as $tenant) {
            $redis->del(
                "flags:{{$tenant}}:uses",
                "flags:{{$tenant}}:version",
                "flags:{$tenant}:enabled",
                "flags:{$tenant}:uses",
                "flags:{$tenant}:version",
            );
        }
    }

    public function test_a_version_release_succeeds_on_the_cluster_and_writes_both_keys(): void
    {
        $this->postJson('/flags/acme/release')->assertOk();

        $redis = $this->redis();

        $this->assertSame(1, (int) $redis->get('flags:{acme}:uses'), 'The release must increment the usage counter.');
        $this->assertNotFalse($redis->get('flags:{acme}:version'), 'The release must write the version marker.');
    }

    public function test_the_release_keys_share_one_cluster_slot(): void
    {
        $redis = $this->redis();

        $slotOf = fn (string $key) => (int) $redis->rawCommand($key, 'CLUSTER', 'KEYSLOT', $key);

        $this->assertSame(
            $slotOf('flags:{acme}:uses'),
            $slotOf('flags:{acme}:version'),
            'The two keys of the atomic release hash to different cluster slots: on a cluster the operation is rejected (CROSSSLOT) or silently de-atomicized. Only the {…} hash tag decides the slot — the keys must share the tenant hash tag.',
        );
    }

    public function test_the_release_writes_only_hash_tagged_keys(): void
    {
        $this->postJson('/flags/acme/release')->assertOk();

        $redis = $this->redis();

        $this->assertFalse(
            $redis->get('flags:acme:uses'),
            'The release wrote to the un-braced key: readers on the cluster would never see the counter. The braces are part of the key name.',
        );
    }

    public function test_releases_of_different_tenants_stay_independent(): void
    {
        $this->postJson('/flags/acme/release')->assertOk();
        $this->postJson('/flags/globex/release')->assertOk();

        $redis = $this->redis();

        $this->assertSame(1, (int) $redis->get('flags:{acme}:uses'), 'A shared/global key would bleed tenants into each other.');
        $this->assertSame(1, (int) $redis->get('flags:{globex}:uses'), 'A shared/global key would bleed tenants into each other.');
    }

    private function redis(): RedisCluster
    {
        return new RedisCluster(null, array_map('trim', explode(',', (string) config('database.redis.seeds'))));
    }
}
