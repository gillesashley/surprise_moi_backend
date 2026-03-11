<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rider>
 */
class RiderFactory extends Factory
{
    protected $model = Rider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '024'.fake()->unique()->numberBetween(1000000, 9999999),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'vehicle_type' => fake()->randomElement(['motorcycle', 'car']),
            'vehicle_category' => fake()->randomElement(['motorbike', 'car']),
            'license_plate' => strtoupper(fake()->bothify('??-####-##')),
            'id_card_number' => 'GHA-'.fake()->numerify('#########'),
            'status' => 'approved',
            'is_active' => true,
            'is_online' => false,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'average_rating' => fake()->randomFloat(2, 3.0, 5.0),
            'total_deliveries' => fake()->numberBetween(0, 200),
        ];
    }

    /**
     * Indicate that the rider has a pending status.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    /**
     * Indicate that the rider is under review.
     */
    public function underReview(): static
    {
        return $this->state(fn () => ['status' => 'under_review']);
    }

    /**
     * Indicate that the rider is approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    /**
     * Indicate that the rider is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    /**
     * Indicate that the rider is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /**
     * Indicate that the rider is online with a location.
     */
    public function online(): static
    {
        return $this->state(fn () => [
            'is_online' => true,
            'current_latitude' => fake()->latitude(5.5, 6.0),
            'current_longitude' => fake()->longitude(-0.3, 0.1),
            'location_updated_at' => now(),
        ]);
    }

    /**
     * Indicate that the rider has uploaded documents.
     */
    public function withDocuments(): static
    {
        return $this->state(fn () => [
            'ghana_card_front' => 'documents/ghana_card_front.jpg',
            'ghana_card_back' => 'documents/ghana_card_back.jpg',
            'drivers_license' => 'documents/drivers_license.jpg',
            'vehicle_photo' => 'documents/vehicle_photo.jpg',
        ]);
    }
}
