<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\RiderWithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RiderWithdrawalRequest>
 */
class RiderWithdrawalRequestFactory extends Factory
{
    protected $model = RiderWithdrawalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'status' => 'pending',
            'mobile_money_provider' => fake()->randomElement(['mtn', 'vodafone', 'airteltigo']),
            'mobile_money_number' => '024'.fake()->numerify('#######'),
        ];
    }

    /**
     * Indicate that the withdrawal request has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
