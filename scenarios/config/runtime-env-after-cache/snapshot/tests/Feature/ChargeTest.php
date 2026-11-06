<?php

namespace Tests\Feature;

use App\Models\Charge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.billing.key', 'test-gateway-key');

        Http::fake([
            'api.billinghub.example/*' => Http::response(['reference' => 'ch_1001'], 200),
        ]);
    }

    public function test_a_charge_is_recorded_with_the_provider_reference(): void
    {
        $response = $this->postJson('/charge', ['customer_id' => 7, 'amount_cents' => 1500]);

        $response->assertOk();
        $response->assertJsonPath('charge.reference', 'ch_1001');

        $this->assertDatabaseHas('charges', [
            'customer_id' => 7,
            'amount_cents' => 1500,
            'provider_reference' => 'ch_1001',
        ]);
    }
}
