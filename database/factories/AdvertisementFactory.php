<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Advertisement>
 */
class AdvertisementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'image_path' => null,
            'link_url' => $this->faker->url(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'scheduled']),
            'placement' => $this->faker->randomElement(['home_banner', 'feed', 'popup', 'sidebar']),
            'target_audience' => null,
            'display_order' => $this->faker->numberBetween(0, 100),
            'start_date' => now()->subDays($this->faker->numberBetween(0, 30)),
            'end_date' => now()->addDays($this->faker->numberBetween(30, 90)),
            'clicks' => $this->faker->numberBetween(0, 1000),
            'impressions' => $this->faker->numberBetween(0, 10000),
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
