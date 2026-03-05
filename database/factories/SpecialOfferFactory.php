<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SpecialOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialOffer>
 */
class SpecialOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'discount_percentage' => $this->faker->numberBetween(5, 50),
            'tag' => $this->faker->randomElement(SpecialOffer::validTags()),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(14),
            'ends_at' => now()->subDay(),
            'is_active' => false,
        ]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
