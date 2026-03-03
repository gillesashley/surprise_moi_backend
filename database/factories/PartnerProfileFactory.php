<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PartnerProfile>
 */
class PartnerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'temperament' => fake()->randomElement(['introverted', 'extroverted', 'adventurous', 'calm', 'creative', 'analytical']),
            'likes' => fake()->randomElements(['reading', 'gardening', 'cooking', 'music', 'sports', 'art', 'travel', 'tech', 'fashion'], 3),
            'dislikes' => fake()->randomElements(['plastic', 'loud music', 'crowds', 'spicy food', 'clutter'], 2),
            'relationship_type' => fake()->randomElement(['spouse', 'partner', 'parent', 'sibling', 'friend', 'colleague']),
            'age_range' => fake()->randomElement(['18-24', '25-30', '31-40', '41-50', '51-60', '60+']),
            'occasion' => fake()->randomElement(['birthday', 'anniversary', 'valentine', 'christmas', 'just because', 'graduation']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
