<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorOnboardingPayment>
 */
class VendorOnboardingPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vendor_application_id' => VendorApplication::factory(),
            'reference' => VendorOnboardingPayment::generateReference(),
            'amount' => $this->faker->randomFloat(2, 50, 200),
            'amount_in_kobo' => fn (array $attrs) => (int) ($attrs['amount'] * 100),
            'currency' => 'GHS',
            'status' => VendorOnboardingPayment::STATUS_PENDING,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorOnboardingPayment::STATUS_SUCCESS,
            'paystack_reference' => 'PSK-'.strtoupper($this->faker->bothify('????####')),
            'channel' => $this->faker->randomElement(['card', 'mobile_money']),
            'paid_at' => now(),
            'verified_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorOnboardingPayment::STATUS_FAILED,
            'failure_reason' => $this->faker->sentence(),
            'verified_at' => now(),
        ]);
    }
}
