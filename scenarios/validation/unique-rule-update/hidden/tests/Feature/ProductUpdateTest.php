<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the update-path uniqueness invariant: the record's own SKU must be
 * excluded from the uniqueness check on update, while every other product's
 * SKU still conflicts.
 */
class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_edited_keeping_its_own_sku(): void
    {
        $product = Product::create(['sku' => 'SKU-1', 'name' => 'Keyboard', 'price_cents' => 4900]);

        $response = $this->patchJson("/products/{$product->id}", [
            'sku' => 'SKU-1',
            'name' => 'Keyboard Pro',
            'price_cents' => 5900,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Keyboard Pro']);
    }

    public function test_a_product_cannot_take_another_products_sku(): void
    {
        Product::create(['sku' => 'SKU-1', 'name' => 'Keyboard', 'price_cents' => 4900]);
        $other = Product::create(['sku' => 'SKU-2', 'name' => 'Mouse', 'price_cents' => 1900]);

        $response = $this->patchJson("/products/{$other->id}", [
            'sku' => 'SKU-1',
            'name' => 'Mouse renamed',
            'price_cents' => 1900,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sku');
    }
}
