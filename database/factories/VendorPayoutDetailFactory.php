<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorPayoutDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorPayoutDetailFactory extends Factory
{
    protected $model = VendorPayoutDetail::class;

    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->vendor(),
            'payout_method' => VendorPayoutDetail::METHOD_MOBILE_MONEY,
            'account_name' => fake()->name(),
            'account_number' => '0'.fake()->numerify('#########'),
            'bank_code' => 'MTN',
            'bank_name' => 'MTN Mobile Money',
            'provider' => 'mtn',
            'paystack_recipient_code' => 'RCP_'.fake()->bothify('??########'),
            'is_verified' => true,
            'is_default' => true,
        ];
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_method' => VendorPayoutDetail::METHOD_BANK_TRANSFER,
            'account_number' => fake()->numerify('##########'),
            'bank_code' => 'GH'.fake()->numerify('###'),
            'bank_name' => fake()->randomElement(['GCB Bank', 'Ecobank Ghana', 'Stanbic Bank', 'Fidelity Bank']),
            'provider' => null,
        ]);
    }
}
