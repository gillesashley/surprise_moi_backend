<?php

namespace Database\Factories;

use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Target>
 */
class TargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 months');

        return [
            'user_id' => User::factory(),
            'assigned_by' => User::factory(),
            'user_role' => $this->faker->randomElement(['field_agent', 'marketer']),
            'target_type' => $this->faker->randomElement([Target::TYPE_VENDOR_SIGNUPS, Target::TYPE_REVENUE_GENERATED]),
            'target_value' => $this->faker->randomFloat(2, 1000, 100000),
            'current_value' => 0,
            'bonus_amount' => $this->faker->randomFloat(2, 100, 5000),
            'overachievement_rate' => $this->faker->randomFloat(2, 0, 50),
            'period_type' => $this->faker->randomElement(['monthly', 'quarterly', 'annual', 'custom']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => Target::STATUS_ACTIVE,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Target::STATUS_COMPLETED,
            'current_value' => $attributes['target_value'],
            'achieved_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Target::STATUS_EXPIRED,
            'end_date' => now()->subDay(),
        ]);
    }
}
