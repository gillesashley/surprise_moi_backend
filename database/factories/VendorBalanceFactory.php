<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorBalance>
 */
class VendorBalanceFactory extends Factory
{
    protected $model = VendorBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->vendor(),
            'available_balance' => fake()->randomFloat(2, 100, 10000),
            'pending_balance' => fake()->randomFloat(2, 0, 2000),
            'total_earned' => fake()->randomFloat(2, 1000, 50000),
            'total_withdrawn' => fake()->randomFloat(2, 0, 5000),
            'currency' => 'GHS',
        ];
    }
}
