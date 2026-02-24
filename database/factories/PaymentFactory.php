<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 50, 1000);
        $amountInKobo = (int) round($amount * 100);

        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'reference' => 'PAY-'.strtoupper(Str::random(16)),
            'paystack_reference' => strtoupper(Str::random(12)),
            'authorization_url' => 'https://checkout.paystack.com/'.Str::random(20),
            'access_code' => Str::random(20),
            'amount' => $amount,
            'amount_in_kobo' => $amountInKobo,
            'currency' => 'GHS',
            'channel' => $this->faker->randomElement(['card', 'mobile_money', 'bank']),
            'payment_method_type' => $this->faker->randomElement(['visa', 'mastercard', 'mtn', 'vodafone']),
            'status' => Payment::STATUS_PENDING,
            'ip_address' => $this->faker->ipv4(),
            'metadata' => [
                'order_id' => $this->faker->randomNumber(5),
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            ],
        ];
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_PENDING,
            'paid_at' => null,
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_SUCCESS,
            'paid_at' => now(),
            'verified_at' => now(),
            'gateway_response' => 'Successful',
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'verified_at' => now(),
            'failure_reason' => $this->faker->randomElement([
                'Insufficient funds',
                'Card declined',
                'Transaction cancelled by user',
                'Network error',
            ]),
            'gateway_response' => 'Failed',
        ]);
    }

    /**
     * Indicate that the payment was abandoned.
     */
    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_ABANDONED,
            'verified_at' => now(),
            'failure_reason' => 'User abandoned the transaction',
            'gateway_response' => 'Abandoned',
        ]);
    }

    /**
     * Indicate that the payment was made with a card.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'card',
            'payment_method_type' => $this->faker->randomElement(['visa', 'mastercard', 'verve']),
            'card_last4' => $this->faker->numerify('####'),
            'card_type' => $this->faker->randomElement(['visa', 'mastercard', 'verve']),
            'card_exp_month' => $this->faker->numberBetween(1, 12),
            'card_exp_year' => $this->faker->numberBetween(date('Y'), date('Y') + 5),
            'card_bank' => $this->faker->randomElement(['GTBank', 'Access Bank', 'First Bank', 'UBA']),
        ]);
    }

    /**
     * Indicate that the payment was made with mobile money.
     */
    public function mobileMoney(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'mobile_money',
            'payment_method_type' => $this->faker->randomElement(['mtn', 'vodafone', 'airteltigo']),
            'mobile_money_number' => '024'.$this->faker->numerify('#######'),
            'mobile_money_provider' => $this->faker->randomElement(['MTN', 'Vodafone', 'AirtelTigo']),
        ]);
    }

    /**
     * Create a payment for a specific order with matching amount.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'amount' => $order->total,
            'amount_in_kobo' => (int) round($order->total * 100),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }
}
