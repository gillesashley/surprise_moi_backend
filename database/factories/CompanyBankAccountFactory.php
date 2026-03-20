<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_name' => fake()->company(),
            'account_number' => fake()->numerify('##########'),
            'bank_code' => fake()->numerify('###'),
            'bank_name' => fake()->randomElement(['Access Bank', 'GTBank', 'First Bank', 'Zenith Bank']),
            'paystack_recipient_code' => 'RCP_'.fake()->bothify('??????????'),
            'is_active' => false,
            'added_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
