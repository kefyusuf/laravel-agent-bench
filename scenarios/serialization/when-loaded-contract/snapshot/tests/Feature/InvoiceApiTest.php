<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['name' => 'Kim']);
        $this->invoice = Invoice::create(['customer_id' => $this->customer->id, 'number' => 'INV-1']);
    }

    public function test_the_invoice_with_include_returns_the_customer(): void
    {
        $response = $this->getJson('/invoices/' . $this->invoice->id . '?include=customer');

        $response->assertOk();
        $this->assertStringContainsString('Kim', $response->getContent());
    }
}
