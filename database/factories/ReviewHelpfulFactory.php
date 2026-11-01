<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewHelpful;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewHelpful>
 */
class ReviewHelpfulFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'user_id' => User::factory(),
        ];
    }
}
