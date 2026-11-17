<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the serialization contract: the customer key appears exactly when
 * the controller included it, and resources never issue queries of their own
 * (verified with strict lazy loading).
 */
class InvoiceSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(true);

        $this->customer = Customer::create(['name' => 'Kim']);
        $this->invoice = Invoice::create(['customer_id' => $this->customer->id, 'number' => 'INV-1']);
    }

    public function test_without_include_the_customer_key_is_absent(): void
    {
        $response = $this->getJson('/invoices/' . $this->invoice->id);

        $response->assertOk();

        $this->assertArrayNotHasKey(
            'customer',
            $response->json(),
            "The 'customer' key appeared in a response without ?include=customer: the resource dereferences the relation unconditionally, breaking the documented serialization contract.",
        );
    }

    public function test_with_include_the_customer_key_is_present(): void
    {
        $response = $this->getJson('/invoices/' . $this->invoice->id . '?include=customer');

        $response->assertOk();
        $this->assertSame('Kim', $response->json('customer.name'));
    }

    protected $customer;
    protected $invoice;
}
