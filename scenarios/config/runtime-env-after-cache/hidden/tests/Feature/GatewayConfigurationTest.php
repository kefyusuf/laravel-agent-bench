<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encodes the configuration contract of the billing gateway under a cached
 * config deployment: configuration comes from the repository, missing
 * credentials fail loudly, and no hardcoded credential defaults exist.
 */
class GatewayConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gateway_honours_config_repository_overrides(): void
    {
        config()->set('services.billing.key', 'injected-live-key');

        Http::fake([
            'api.billinghub.example/*' => Http::response(['reference' => 'ch_ok'], 200),
        ]);

        $this->postJson('/charge', ['customer_id' => 7, 'amount_cents' => 1500])->assertOk();

        $authorization = null;

        Http::assertSent(function ($request) use (&$authorization) {
            $authorization = $request->header('Authorization');

            return true;
        });

        $this->assertContains(
            'Bearer injected-live-key',
            (array) $authorization,
            'The gateway must take its credential from the config repository (services.billing.key); reading env() at runtime ignores repository overrides and breaks under config:cache.',
        );
    }

    public function test_a_missing_credential_fails_loudly_instead_of_using_a_default(): void
    {
        config()->set('services.billing.key', null);

        Http::fake([
            'api.billinghub.example/*' => Http::response(['reference' => 'ch_should_not_happen'], 200),
        ]);

        $response = $this->postJson('/charge', ['customer_id' => 7, 'amount_cents' => 1500]);

        $this->assertTrue(
            $response->isServerError(),
            'A missing gateway credential must fail loudly (HTTP 5xx); the request instead succeeded with HTTP '.$response->status().', i.e. a hardcoded default credential was used.',
        );

        Http::assertNothingSent();
    }

    public function test_no_config_file_ships_a_hardcoded_credential_default(): void
    {
        $servicesPath = config_path('services.php');

        if (file_exists($servicesPath)) {
            $this->assertStringNotContainsString(
                'sandbox-key',
                (string) file_get_contents($servicesPath),
                'A hardcoded credential default was reintroduced into the config file.',
            );
        }

        $this->addToAssertionCount(1);
    }
}
