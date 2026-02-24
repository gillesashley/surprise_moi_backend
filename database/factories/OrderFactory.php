<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50, 1000);
        $discountAmount = $this->faker->randomFloat(2, 0, $subtotal * 0.3);
        $deliveryFee = $this->faker->randomFloat(2, 0, 50);
        $total = $subtotal - $discountAmount + $deliveryFee;

        return [
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'vendor_id' => $this->faker->optional(0.7)->randomElement([User::factory()->create(['role' => 'vendor'])->id, null]),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'coupon_id' => $this->faker->optional(0.3)->randomElement([Coupon::factory()->create()->id, null]),
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'currency' => 'GHS',
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'processing', 'fulfilled', 'delivered']),
            'delivery_address_id' => Address::factory(),
            'special_instructions' => $this->faker->optional(0.4)->sentence(),
            'scheduled_datetime' => $this->faker->optional(0.3)->dateTimeBetween('+1 day', '+7 days'),
            'tracking_number' => $this->faker->optional(0.5)->bothify('TRK-########'),
        ];
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'confirmed_at' => null,
                'fulfilled_at' => null,
                'delivered_at' => null,
            ];
        });
    }

    public function confirmed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ];
        });
    }

    public function fulfilled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'fulfilled',
                'confirmed_at' => now()->subDays(2),
                'fulfilled_at' => now(),
            ];
        });
    }

    public function delivered(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'delivered',
                'confirmed_at' => now()->subDays(3),
                'fulfilled_at' => now()->subDays(1),
                'delivered_at' => now(),
            ];
        });
    }
}
