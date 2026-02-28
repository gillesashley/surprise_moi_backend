<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reviewableModelClass = $this->faker->randomElement([Product::class, Service::class]);
        $reviewableType = (new $reviewableModelClass)->getMorphClass();
        $userId = User::factory();
        $itemId = $reviewableModelClass::factory();
        $rating = $this->faker->randomElement([
            1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0,
        ]);

        return [
            'user_id' => $userId,
            'item_type' => $reviewableType,
            'item_id' => $itemId,
            'reviewable_type' => $reviewableType,
            'reviewable_id' => $itemId,
            'rating' => $rating,
            'comment' => $this->faker->optional(0.95)->paragraph(),
            'images' => $this->faker->optional(0.3)->randomElements([
                'reviews/image1.jpg',
                'reviews/image2.jpg',
                'reviews/image3.jpg',
            ], $this->faker->numberBetween(1, 3)),
            'is_verified_purchase' => $this->faker->boolean(30),
            'helpful_count' => 0,
            'order_id' => null,
            'context_key' => null,
        ];
    }

    /**
     * Indicate that the review is for a product.
     */
    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => (new Product)->getMorphClass(),
            'item_id' => $product?->id ?? Product::factory(),
            'reviewable_type' => (new Product)->getMorphClass(),
            'reviewable_id' => $product?->id ?? Product::factory(),
        ]);
    }

    /**
     * Indicate that the review is for a service.
     */
    public function forService(?Service $service = null): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => (new Service)->getMorphClass(),
            'item_id' => $service?->id ?? Service::factory(),
            'reviewable_type' => (new Service)->getMorphClass(),
            'reviewable_id' => $service?->id ?? Service::factory(),
        ]);
    }

    /**
     * Indicate that the review is a verified purchase.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => true,
        ]);
    }

    /**
     * Indicate that the review is not a verified purchase.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => false,
        ]);
    }

    /**
     * Set a specific rating.
     */
    public function withRating(float $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => min(5.0, max(1.0, $rating)),
        ]);
    }

    /**
     * Set review with images.
     */
    public function withImages(array $images = ['reviews/test1.jpg', 'reviews/test2.jpg']): static
    {
        return $this->state(fn (array $attributes) => [
            'images' => $images,
        ]);
    }
}
