<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewImage>
 */
class ReviewImageFactory extends Factory
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
            'storage_path' => 'reviews/images/'.$this->faker->uuid.'.jpg',
            'sort_order' => 1,
        ];
    }
}
