<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the bucket-scoping invariant of the OTP limiter: per-identity buckets,
 * keyed by something the server controls.
 */
class OtpThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_visitor_exhausting_the_limit_does_not_lock_out_another(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        foreach (range(1, 3) as $i) {
            $this->postJson('/request-otp', ['email' => 'attacker@example.test'])->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

        $response = $this->postJson('/request-otp', ['email' => 'victim@example.test']);

        $this->assertSame(
            200,
            $response->status(),
            'Visitor B received HTTP '.$response->status().': the OTP limiter is sharing one bucket across all callers instead of keying per identity.',
        );
    }

    public function test_a_spoofed_forwarded_header_cannot_mint_fresh_buckets(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);

        foreach (range(1, 3) as $i) {
            $this->postJson(
                '/request-otp',
                ['email' => 'attacker@example.test'],
                ['X-Forwarded-For' => '203.0.113.7'],
            )->assertOk();
        }

        $response = $this->postJson(
            '/request-otp',
            ['email' => 'attacker@example.test'],
            ['X-Forwarded-For' => '198.51.100.99'],
        );

        $this->assertSame(
            429,
            $response->status(),
            'Rotating X-Forwarded-For must not bypass the limit; the bucket key must come from a server-controlled identity.',
        );
    }
}
