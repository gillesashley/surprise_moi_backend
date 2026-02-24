<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(['percentage', 'fixed', 'cashback']);
        $value = match ($type) {
            'percentage' => $this->faker->numberBetween(5, 50),
            'fixed' => $this->faker->numberBetween(10, 100),
            'cashback' => $this->faker->numberBetween(5, 50),
        };

        return [
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'title' => $this->faker->words(3, true),
            'type' => $type,
            'value' => $value,
            'min_purchase_amount' => $this->faker->optional(0.7)->randomFloat(2, 50, 500),
            'max_discount_amount' => $type === 'percentage' ? $this->faker->optional(0.5)->randomFloat(2, 20, 200) : null,
            'currency' => 'GHS',
            'usage_limit' => $this->faker->optional(0.6)->numberBetween(10, 500),
            'used_count' => 0,
            'user_limit_per_user' => $this->faker->numberBetween(1, 5),
            'valid_from' => now(),
            'valid_until' => now()->addDays($this->faker->numberBetween(7, 90)),
            'is_active' => $this->faker->boolean(85),
            'vendor_id' => null,
            'applicable_to' => $this->faker->randomElement(['all', 'products', 'services']),
            'description' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    public function forVendor(?User $vendor = null): self
    {
        return $this->state(function (array $attributes) use ($vendor) {
            return [
                'vendor_id' => $vendor?->id ?? User::factory()->create(['role' => 'vendor'])->id,
            ];
        });
    }

    public function expired(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'valid_from' => now()->subDays(30),
                'valid_until' => now()->subDays(1),
                'is_active' => false,
            ];
        });
    }

    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addDays(30),
            ];
        });
    }
}
