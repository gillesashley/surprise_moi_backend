<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'cart_token' => Str::uuid(),
            'currency' => 'GHS',
            'subtotal_cents' => 0,
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 0,
            'metadata' => null,
            'version' => 1,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'cart_token' => null,
        ]);
    }
}
