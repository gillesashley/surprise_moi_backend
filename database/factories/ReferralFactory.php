<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Referral>
 */
class ReferralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referral_code_id' => ReferralCode::factory(),
            'influencer_id' => User::factory(),
            'vendor_id' => User::factory(),
            'vendor_application_id' => null,
            'status' => Referral::STATUS_PENDING,
            'earned_amount' => 0,
            'activated_at' => null,
            'commission_expires_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Referral::STATUS_ACTIVE,
            'activated_at' => now(),
            'commission_expires_at' => now()->addMonths(6),
        ]);
    }

    public function withVendorApplication(): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_application_id' => VendorApplication::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Referral::STATUS_EXPIRED,
            'activated_at' => now()->subYear(),
            'commission_expires_at' => now()->subDay(),
        ]);
    }
}
