<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorTransaction>
 */
class VendorTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->vendor(),
            'order_id' => Order::factory(),
            'transaction_number' => 'VTX-'.strtoupper(Str::random(10)),
            'type' => $this->faker->randomElement([
                VendorTransaction::TYPE_CREDIT_SALE,
                VendorTransaction::TYPE_RELEASE_FUNDS,
                VendorTransaction::TYPE_PAYOUT,
            ]),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'GHS',
            'status' => VendorTransaction::STATUS_COMPLETED,
            'description' => $this->faker->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorTransaction::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorTransaction::STATUS_COMPLETED,
        ]);
    }
}
