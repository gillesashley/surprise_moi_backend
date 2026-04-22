<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPayoutDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPayoutDetailFactory extends Factory
{
    protected $model = UserPayoutDetail::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'payout_method' => 'mobile_money',
            'mobile_money_number' => '024'.fake()->numerify('#######'),
            'mobile_money_provider' => fake()->randomElement(['mtn', 'vodafone', 'airteltigo']),
            'account_name' => fake()->name(),
            'paystack_recipient_code' => 'RCP_'.fake()->bothify('??????????'),
            'is_verified' => true,
            'is_default' => true,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn () => [
            'is_verified' => false,
            'paystack_recipient_code' => null,
        ]);
    }
}
