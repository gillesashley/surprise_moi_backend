<?php

namespace Database\Factories;

use App\Models\Earning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Earning>
 */
class EarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userRoles = ['influencer', 'field_agent', 'marketer'];
        $earningTypes = [
            Earning::TYPE_REFERRAL_BONUS,
            Earning::TYPE_COMMISSION,
            Earning::TYPE_TARGET_BONUS,
            Earning::TYPE_OVERACHIEVEMENT_BONUS,
            Earning::TYPE_SIGN_ON_BONUS,
        ];

        return [
            'user_id' => User::factory(),
            'user_role' => fake()->randomElement($userRoles),
            'earning_type' => fake()->randomElement($earningTypes),
            'earnable_id' => fake()->randomNumber(),
            'earnable_type' => fake()->randomElement([
                'App\\Models\\Order',
                'App\\Models\\Referral',
                'App\\Models\\TargetAchievement',
            ]),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'GHS',
            'status' => Earning::STATUS_PENDING,
            'description' => fake()->optional()->sentence(),
            'earned_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Earning::STATUS_PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Earning::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Earning::STATUS_PAID,
            'approved_at' => now(),
            'approved_by' => User::factory(),
            'paid_at' => now(),
        ]);
    }
}
