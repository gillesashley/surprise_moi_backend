<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryRequest>
 */
class DeliveryRequestFactory extends Factory
{
    protected $model = DeliveryRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => User::factory()->vendor(),
            'rider_id' => null,
            'assigned_rider_id' => null,
            'status' => 'broadcasting',
            'pickup_address' => fake()->address(),
            'pickup_latitude' => fake()->latitude(5.5, 6.0),
            'pickup_longitude' => fake()->longitude(-0.3, 0.1),
            'dropoff_address' => fake()->address(),
            'dropoff_latitude' => fake()->latitude(5.5, 6.0),
            'dropoff_longitude' => fake()->longitude(-0.3, 0.1),
            'delivery_fee' => fake()->randomFloat(2, 10, 100),
            'distance_km' => fake()->randomFloat(2, 1, 30),
            'broadcast_radius_km' => 5.00,
            'broadcast_attempts' => 0,
            'expires_at' => now()->addSeconds(30),
        ];
    }

    /**
     * Indicate that the delivery request has been accepted by a rider.
     */
    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'rider_id' => Rider::factory(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the delivery has been picked up.
     */
    public function pickedUp(): static
    {
        return $this->state(fn () => [
            'status' => 'picked_up',
            'rider_id' => Rider::factory(),
            'accepted_at' => now()->subMinutes(10),
            'picked_up_at' => now(),
        ]);
    }

    /**
     * Indicate that the delivery has been completed.
     */
    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'rider_id' => Rider::factory(),
            'accepted_at' => now()->subMinutes(30),
            'picked_up_at' => now()->subMinutes(20),
            'delivered_at' => now(),
        ]);
    }
}
