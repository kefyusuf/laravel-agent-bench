<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the cross-tenant authorization invariant: an authenticated user must
 * never be able to read another company's invoice, and legitimate access must
 * keep working.
 */
class InvoiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_read_another_companys_invoice(): void
    {
        $acme = Customer::create(['name' => 'Acme']);
        $globex = Customer::create(['name' => 'Globex']);

        $secret = Invoice::create(['customer_id' => $globex->id, 'number' => 'INV-SECRET', 'total_cents' => 99000]);

        $attacker = User::create(['name' => 'Insider', 'email' => 'staff@acme.test', 'password' => 'secret', 'customer_id' => $acme->id]);

        $response = $this->actingAs($attacker)->getJson("/customers/{$acme->id}/invoices/{$secret->id}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "A staff member read another company's invoice (HTTP {$response->status()}); invoice access must be authorized against the user's company, not just authenticated.",
        );
        $this->assertStringNotContainsString('INV-SECRET', (string) $response->getContent());
    }

    public function test_legitimate_access_keeps_working(): void
    {
        $acme = Customer::create(['name' => 'Acme']);
        $invoice = Invoice::create(['customer_id' => $acme->id, 'number' => 'INV-OWN', 'total_cents' => 250]);
        $user = User::create(['name' => 'Staff', 'email' => 'staff@acme.test', 'password' => 'secret', 'customer_id' => $acme->id]);

        $this->actingAs($user)
            ->getJson("/customers/{$acme->id}/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('invoice.number', 'INV-OWN');
    }
}
