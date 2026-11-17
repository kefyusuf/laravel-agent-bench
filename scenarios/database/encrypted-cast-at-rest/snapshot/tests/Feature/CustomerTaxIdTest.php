<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTaxIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tax_id_can_be_stored_and_read_back(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'tax_id' => 'TAX-123']);

        $response = $this->getJson("/customers/{$customer->id}/tax-id");

        $response->assertOk();
        $response->assertJson(['tax_id' => 'TAX-123']);
    }

    public function test_a_tax_id_can_be_updated(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'tax_id' => 'TAX-123']);

        $response = $this->patchJson("/customers/{$customer->id}/tax-id", ['tax_id' => 'TAX-456']);

        $response->assertOk();
        $response->assertJson(['tax_id' => 'TAX-456']);
    }
}
