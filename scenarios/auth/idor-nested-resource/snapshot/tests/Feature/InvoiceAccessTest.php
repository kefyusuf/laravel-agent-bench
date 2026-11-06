<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_invoices(): void
    {
        $customer = Customer::create(['name' => 'Acme']);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-1', 'total_cents' => 100]);

        $this->getJson("/customers/{$customer->id}/invoices/{$invoice->id}")->assertStatus(401);
    }

    public function test_staff_can_open_an_invoice_of_their_own_company(): void
    {
        $customer = Customer::create(['name' => 'Acme']);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-2', 'total_cents' => 250]);
        $user = User::create(['name' => 'Staff', 'email' => 'staff@acme.test', 'password' => 'secret', 'customer_id' => $customer->id]);

        $response = $this->actingAs($user)->getJson("/customers/{$customer->id}/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJsonPath('invoice.number', 'INV-2');
    }
}
