<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chargeStart = $this->faker->randomFloat(2, 100, 1000);

        return [
            'vendor_id' => \App\Models\User::factory(),
            'shop_id' => \App\Models\Shop::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'service_type' => $this->faker->randomElement([
                'instrumentalist',
                'event-planner',
                'photographer',
                'videographer',
                'decorator',
                'caterer',
            ]),
            'charge_start' => $chargeStart,
            'charge_end' => $this->faker->boolean(70) ? $chargeStart * 1.5 : null,
            'currency' => 'GHS',
            'thumbnail' => 'storage/services/'.$this->faker->slug().'.jpg',
            'availability' => $this->faker->randomElement(['available', 'unavailable', 'booked']),
            'rating' => $this->faker->randomFloat(2, 3, 5),
            'reviews_count' => $this->faker->numberBetween(0, 200),
        ];
    }
}
