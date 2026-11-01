<?php

namespace Database\Factories;

use App\Models\PersonalityTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalityTrait>
 */
class PersonalityTraitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'icon' => $this->faker->word(),
        ];
    }
}
