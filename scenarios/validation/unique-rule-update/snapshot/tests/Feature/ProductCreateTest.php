<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_created_with_a_unique_sku(): void
    {
        $response = $this->postJson('/products', [
            'sku' => 'SKU-1',
            'name' => 'Keyboard',
            'price_cents' => 4900,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['sku' => 'SKU-1']);
    }

    public function test_a_duplicate_sku_is_rejected_on_create(): void
    {
        Product::create(['sku' => 'SKU-1', 'name' => 'Keyboard', 'price_cents' => 4900]);

        $response = $this->postJson('/products', [
            'sku' => 'SKU-1',
            'name' => 'Keyboard 2',
            'price_cents' => 4900,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sku');
    }
}
