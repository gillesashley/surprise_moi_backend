<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicGenre>
 */
class MusicGenreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Pop',
                'Rock',
                'Hip Hop',
                'R&B',
                'Jazz',
                'Classical',
                'Electronic',
                'Country',
                'Reggae',
                'Blues',
                'Folk',
                'Soul',
                'Funk',
                'Metal',
                'Punk',
                'Indie',
                'Alternative',
                'Latin',
                'Gospel',
                'Afrobeats',
                'K-Pop',
                'Highlife',
            ]),
            'icon' => $this->faker->randomElement(['🎵', '🎸', '🎤', '🎹', '🎷', '🎺', '🥁', '🎻']),
        ];
    }
}
