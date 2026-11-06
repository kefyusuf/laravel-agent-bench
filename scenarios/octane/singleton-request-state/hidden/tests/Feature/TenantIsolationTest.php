<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the request-isolation invariant. Both requests are handled by the same
 * application instance; between them we flush scoped instances exactly the way
 * Octane's FlushTemporaryContainerInstances listener does between worker requests.
 * Per-request state must therefore be re-resolved — a singleton that captured the
 * first request stays frozen even after the Octane-style flush.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_sequential_requests_each_see_their_own_tenant(): void
    {
        $acme = Tenant::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex Ltd', 'slug' => 'globex']);

        $acme->orders()->createMany([['reference' => 'A-1'], ['reference' => 'A-2']]);
        $globex->orders()->createMany([['reference' => 'G-1']]);

        $first = $this->withHeaders(['X-Tenant' => 'acme'])->getJson('/dashboard');
        $first->assertOk();
        $this->assertSame('Acme Corp', $first->json('tenant'));
        $this->assertSame(2, $first->json('orders'));

        // Octane flushes scoped instances between worker requests; singletons survive.
        $this->app->forgetScopedInstances();

        $second = $this->withHeaders(['X-Tenant' => 'globex'])->getJson('/dashboard');
        $second->assertOk();

        $this->assertSame(
            'Globex Ltd',
            $second->json('tenant'),
            'The second request observed tenant "'.$second->json('tenant').'": the tenant context was resolved once and frozen, so every later request handled by the same process inherits the first request\'s tenant.',
        );

        $this->assertSame(
            1,
            $second->json('orders'),
            'The second request received another tenant\'s order totals.',
        );
    }
}
