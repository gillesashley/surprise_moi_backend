<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $hashed_password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = $this->faker ?? \Faker\Factory::create();

        // Array of Unsplash human profile images
        $avatars = [
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1517174925202-24c23cb6266b?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1463746675571-6f89d64a2e33?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1507009596925-d0a0445a043f?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1517849845537-1d51a20414de?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop',
        ];

        return [
            'name' => $faker->name(),
            'email' => $faker->unique()->safeEmail(),
            'phone' => '024'.$faker->unique()->numberBetween(1000000, 9999999),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => (static::$hashed_password ??= bcrypt("password")),
            'role' => 'customer',
            'avatar' => $faker->randomElement($avatars),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model's phone number should be unverified.
     */
    public function phoneUnverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a vendor.
     */
    public function vendor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'vendor',
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is an influencer.
     */
    public function influencer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'influencer',
        ]);
    }

    /**
     * Indicate that the user is a field agent.
     */
    public function fieldAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'field_agent',
        ]);
    }

    /**
     * Indicate that the user is a marketer.
     */
    public function marketer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'marketer',
        ]);
    }
}
