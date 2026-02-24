<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement([
                Report::CATEGORY_ORDER_ISSUE,
                Report::CATEGORY_PRODUCT_PROBLEM,
                Report::CATEGORY_VENDOR_DISPUTE,
                Report::CATEGORY_PAYMENT_ISSUE,
                Report::CATEGORY_OTHER,
            ]),
            'description' => $this->faker->paragraph(),
            'status' => Report::STATUS_PENDING,
            'order_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Report::STATUS_PENDING,
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Report::STATUS_IN_PROGRESS,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Report::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
            'resolution_notes' => $this->faker->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Report::STATUS_CANCELLED,
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }
}
