<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 20, 1000);
        $hasDiscount = $this->faker->boolean(30);

        return [
            'slug' => Str::random(16),
            'category_id' => \App\Models\Category::factory(),
            'vendor_id' => \App\Models\User::factory(),
            'shop_id' => \App\Models\Shop::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'detailed_description' => $this->faker->paragraph(3),
            'price' => $price,
            'discount_price' => $hasDiscount ? $price * 0.85 : null,
            'discount_percentage' => $hasDiscount ? 15 : null,
            'currency' => 'GHS',
            'thumbnail' => 'storage/products/'.$this->faker->slug().'.jpg',
            'stock' => $this->faker->numberBetween(0, 100),
            'is_available' => $this->faker->boolean(90),
            'is_featured' => $this->faker->boolean(20),
            'rating' => $this->faker->randomFloat(2, 3, 5),
            'reviews_count' => $this->faker->numberBetween(0, 500),
            'sizes' => $this->faker->randomElement([
                null,
                ['Small', 'Medium', 'Large'],
                ['S', 'M', 'L', 'XL'],
            ]),
            'colors' => $this->faker->randomElement([
                null,
                ['Red', 'Blue', 'Green'],
                ['Black', 'White', 'Pink'],
            ]),
            'free_delivery' => $this->faker->boolean(30),
            'delivery_fee' => $this->faker->boolean(70) ? $this->faker->randomFloat(2, 5, 50) : 0,
            'estimated_delivery_days' => $this->faker->randomElement(['Same day', '1-2 days', '2-3 days', '3-5 days']),
            'return_policy' => '14 days return policy',
        ];
    }
}
