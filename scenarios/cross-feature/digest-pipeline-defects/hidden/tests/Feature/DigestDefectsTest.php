<?php

namespace Tests\Feature;

use App\Models\Digest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * One hidden test per defect; the suite is green only when all three are fixed.
 * Each test declares its own HTTP fake; customers are distinct per test so the
 * throttle buckets (array cache, per-process) do not leak between cases.
 */
class DigestDefectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_pdfs_consent_really_persists(): void
    {
        $this->postJson('/digest/settings', [
            'customer_id' => 101,
            'locale' => 'de',
            'attach_pdfs' => true,
        ])->assertOk();

        $this->assertDatabaseHas('digest_settings', [
            'customer_id' => 101,
            'locale' => 'de',
            'attach_pdfs' => true,
        ]);
    }

    public function test_one_customer_exhausting_the_limit_does_not_block_another(): void
    {
        Http::fake([
            'api.ledgermail.example/*' => Http::response(['digest_id' => 'lm_h2'], 200),
        ]);

        // digest limiter: 2 requests per minute per customer.
        $this->postJson('/digest/201/send')->assertOk();
        $this->postJson('/digest/201/send')->assertOk();

        $response = $this->postJson('/digest/202/send');

        $this->assertSame(
            200,
            $response->status(),
            'Customer 202 received HTTP '.$response->status().': the send limiter shares one bucket across all customers instead of keying per customer.',
        );
    }

    public function test_a_failed_billing_call_never_marks_the_digest_sent(): void
    {
        Http::fake([
            'api.ledgermail.example/*' => Http::response(['error' => 'billing api degraded'], 500),
        ]);

        $this->postJson('/digest/203/send');

        $this->assertDatabaseMissing('digests', [
            'customer_id' => 203,
            'status' => Digest::STATUS_SENT,
        ]);
    }
}
