<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateTest extends TestCase
{
    public function test_a_rate_is_returned(): void
    {
        Http::fake([
            'api.rates.example/*' => Http::response(['rate' => 1.08], 200),
        ]);

        $response = $this->getJson('/rates/USD/EUR');

        $response->assertOk();
        $response->assertJson(['rate' => 1.08]);
    }
}
