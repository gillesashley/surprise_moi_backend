<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        $uniqueSlug = \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->randomNumber(6);

        return [
            'name' => ucwords($name),
            'slug' => $uniqueSlug,
            'type' => $this->faker->randomElement(['product', 'service']),
            'description' => $this->faker->sentence(),
            'icon' => 'storage/icons/'.$this->faker->slug().'.png',
            'image' => 'storage/categories/'.$this->faker->slug().'.jpg',
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
