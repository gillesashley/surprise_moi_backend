<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = ['Home', 'Office', 'Parent\'s House', 'Friend\'s House', 'Work', null];

        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->randomElement($names),
            'receiver_name' => $this->faker->optional()->name(),
            'address_line_1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => $this->faker->randomElement(['US', 'GH', 'GB', 'CA', 'NG', 'KE']),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the address is the default address.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
