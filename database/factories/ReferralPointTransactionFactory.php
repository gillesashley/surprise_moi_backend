<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReferralPointTransaction>
 */
class ReferralPointTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'referral_id' => Referral::factory(),
            'points' => 100,
            'reason' => ReferralPointTransaction::REASON_VENDOR_ONBOARDING,
            'description' => 'Points for vendor onboarding',
        ];
    }
}
