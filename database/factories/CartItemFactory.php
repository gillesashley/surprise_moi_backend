<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPriceCents = (int) ($product->price * 100);

        return [
            'cart_id' => Cart::factory(),
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'sku' => null,
            'name' => $product->name,
            'unit_price_cents' => $unitPriceCents,
            'quantity' => $quantity,
            'line_total_cents' => $unitPriceCents * $quantity,
            'metadata' => null,
        ];
    }
}
