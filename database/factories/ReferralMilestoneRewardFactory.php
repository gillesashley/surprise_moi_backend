<?php

namespace Database\Factories;

use App\Models\ReferralMilestoneReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReferralMilestoneReward>
 */
class ReferralMilestoneRewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $threshold = $this->faker->randomElement([1000, 5000, 10000, 15000, 20000]);

        return [
            'user_id' => User::factory(),
            'threshold' => $threshold,
            'points_at_milestone' => $threshold + $this->faker->numberBetween(0, 499),
            'status' => ReferralMilestoneReward::STATUS_PENDING,
            'reward_type' => null,
            'reward_description' => null,
            'reward_value' => null,
            'fulfilled_by' => null,
            'fulfilled_at' => null,
            'admin_notes' => null,
        ];
    }

    /**
     * State: fulfilled reward with a fake fulfilling admin.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReferralMilestoneReward::STATUS_FULFILLED,
            'reward_type' => 'gift',
            'reward_description' => 'Power bank',
            'reward_value' => 150.00,
            'fulfilled_by' => User::factory()->admin(),
            'fulfilled_at' => now(),
        ]);
    }
}
