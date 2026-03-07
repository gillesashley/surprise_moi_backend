<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WawVideo>
 */
class WawVideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->state(['role' => 'vendor']),
            'video_url' => 'waw-videos/'.fake()->randomNumber(5).'/'.fake()->unixTime().'.mp4',
            'thumbnail_url' => null,
            'caption' => fake()->sentence(8),
            'product_id' => null,
            'service_id' => null,
            'likes_count' => 0,
            'views_count' => 0,
        ];
    }

    public function withThumbnail(): static
    {
        return $this->state(fn () => [
            'thumbnail_url' => 'waw-videos/'.fake()->randomNumber(5).'/thumbs/'.fake()->unixTime().'.jpg',
        ]);
    }

    public function withProduct(?Product $product = null): static
    {
        return $this->state(fn () => [
            'product_id' => $product?->id ?? Product::factory(),
        ]);
    }

    public function withService(?Service $service = null): static
    {
        return $this->state(fn () => [
            'service_id' => $service?->id ?? Service::factory(),
        ]);
    }

    public function withLikes(int $count = 1): static
    {
        return $this->state(fn () => [
            'likes_count' => $count,
        ]);
    }
}
