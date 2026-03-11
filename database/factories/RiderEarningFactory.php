<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RiderEarning>
 */
class RiderEarningFactory extends Factory
{
    protected $model = RiderEarning::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'order_id' => Order::factory(),
            'delivery_request_id' => DeliveryRequest::factory(),
            'amount' => fake()->randomFloat(2, 10, 100),
            'type' => 'delivery_fee',
            'status' => 'pending',
            'available_at' => now()->addHours(24),
        ];
    }

    /**
     * Indicate that the earning is available for withdrawal.
     */
    public function available(): static
    {
        return $this->state(fn () => [
            'status' => 'available',
            'available_at' => now()->subHour(),
        ]);
    }
}
