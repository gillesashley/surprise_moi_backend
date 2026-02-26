<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'vendor_id' => \App\Models\User::factory(),
            'category_id' => \App\Models\Category::factory(),
            'name' => $name,
            'owner_name' => $this->faker->name(),
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->paragraph(),
            'logo' => 'storage/shops/'.$this->faker->slug().'.jpg',
            'is_active' => $this->faker->boolean(90),
            'location' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
        ];
    }
}
