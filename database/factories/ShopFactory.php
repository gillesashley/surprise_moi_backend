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
            'slug' => \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->randomNumber(6),
            'description' => $this->faker->paragraph(),
            'logo' => 'storage/shops/'.$this->faker->slug().'.jpg',
            'is_active' => $this->faker->boolean(90),
            'location' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
        ];
    }

    /**
     * Set custom service hours on the shop.
     *
     * @param  array<string, array{is_open: bool, open: string|null, close: string|null}>|null  $hours
     */
    public function withServiceHours(?array $hours = null): static
    {
        return $this->state(fn (array $attributes) => [
            'service_hours' => $hours ?? [
                'monday'    => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
                'tuesday'   => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
                'wednesday' => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
                'thursday'  => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
                'friday'    => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
                'saturday'  => ['is_open' => false, 'open' => null,    'close' => null],
                'sunday'    => ['is_open' => false, 'open' => null,    'close' => null],
            ],
        ]);
    }
}
