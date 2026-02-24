<?php

namespace Database\Factories;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userRoles = ['influencer', 'field_agent', 'marketer'];
        $payoutMethod = fake()->randomElement([
            PayoutRequest::METHOD_MOBILE_MONEY,
            PayoutRequest::METHOD_BANK_TRANSFER,
            PayoutRequest::METHOD_QUARTERLY_SALARY,
        ]);

        $attributes = [
            'user_id' => User::factory(),
            'user_role' => fake()->randomElement($userRoles),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'GHS',
            'payout_method' => $payoutMethod,
            'status' => PayoutRequest::STATUS_PENDING,
            'notes' => fake()->optional()->sentence(),
        ];

        // Add method-specific fields
        if ($payoutMethod === PayoutRequest::METHOD_MOBILE_MONEY) {
            $attributes['mobile_money_number'] = fake()->numerify('0#########');
            $attributes['mobile_money_provider'] = fake()->randomElement(['MTN', 'Vodafone', 'AirtelTigo']);
        } elseif ($payoutMethod === PayoutRequest::METHOD_BANK_TRANSFER) {
            $attributes['bank_name'] = fake()->randomElement(['GCB Bank', 'Ecobank', 'Zenith Bank', 'Stanbic Bank']);
            $attributes['account_number'] = fake()->numerify('##########');
            $attributes['account_name'] = fake()->name();
        }

        return $attributes;
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutRequest::STATUS_PENDING,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutRequest::STATUS_PROCESSING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutRequest::STATUS_APPROVED,
            'processed_by' => User::factory(),
            'processed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => fake()->sentence(),
            'processed_by' => User::factory(),
            'processed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutRequest::STATUS_PAID,
            'processed_by' => User::factory(),
            'processed_at' => now(),
            'paid_at' => now(),
        ]);
    }

    public function mobileMoney(): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_method' => PayoutRequest::METHOD_MOBILE_MONEY,
            'mobile_money_number' => fake()->numerify('0#########'),
            'mobile_money_provider' => fake()->randomElement(['MTN', 'Vodafone', 'AirtelTigo']),
            'bank_name' => null,
            'account_number' => null,
            'account_name' => null,
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payout_method' => PayoutRequest::METHOD_BANK_TRANSFER,
            'bank_name' => fake()->randomElement(['GCB Bank', 'Ecobank', 'Zenith Bank', 'Stanbic Bank']),
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'mobile_money_number' => null,
            'mobile_money_provider' => null,
        ]);
    }
}
