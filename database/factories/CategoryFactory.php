<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
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
        $uniqueSlug = Str::slug($name).'-'.$this->faker->unique()->randomNumber(6);

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
