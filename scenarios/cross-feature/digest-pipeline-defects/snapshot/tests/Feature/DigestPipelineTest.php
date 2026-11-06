<?php

namespace Tests\Feature;

use App\Models\Digest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigestPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.ledgermail.example/*' => Http::response(['digest_id' => 'lm_1001'], 200),
        ]);
    }

    public function test_digest_settings_can_be_saved(): void
    {
        $response = $this->postJson('/digest/settings', [
            'customer_id' => 7,
            'locale' => 'de',
            'attach_pdfs' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('digest_settings', ['customer_id' => 7, 'locale' => 'de']);
    }

    public function test_a_send_queues_the_digest_and_marks_it_sent(): void
    {
        $response = $this->postJson('/digest/7/send');

        $response->assertOk();

        $this->assertDatabaseHas('digests', [
            'customer_id' => 7,
            'status' => Digest::STATUS_SENT,
            'provider_digest_id' => 'lm_1001',
        ]);
    }

    public function test_the_send_endpoint_is_rate_limited(): void
    {
        // digest limiter: 2 requests per minute — the third is throttled.
        $this->postJson('/digest/7/send')->assertOk();
        $this->postJson('/digest/7/send')->assertOk();
        $this->postJson('/digest/7/send')->assertStatus(429);
    }
}
