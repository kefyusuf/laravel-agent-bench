<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_fourth_request_within_a_minute_is_throttled(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/request-otp', ['email' => 'user@example.test'])
                ->assertOk();
        }

        $this->postJson('/request-otp', ['email' => 'user@example.test'])
            ->assertStatus(429);
    }
}
