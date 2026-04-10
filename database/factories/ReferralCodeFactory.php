<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'influencer_id' => User::factory()->influencer(),
            'code' => strtoupper(Str::random(8)),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'usage_count' => 0,
            'max_usages' => null,
            'registration_bonus' => 50.00,
            'commission_rate' => 0,
            'commission_duration_months' => 3,
            'discount_percentage' => 0,
            'expires_at' => null,
        ];
    }
}
