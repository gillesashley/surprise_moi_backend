<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorApplication>
 */
class VendorApplicationFactory extends Factory
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
            'status' => VendorApplication::STATUS_PENDING,
            'current_step' => 1,
            'completed_step' => 0,
        ];
    }

    /**
     * Application with Ghana Card uploaded (Step 1 completed).
     */
    public function withGhanaCard(): static
    {
        return $this->state(fn(array $attributes) => [
            'ghana_card_front' => 'vendor-applications/ghana-cards/front-' . $this->faker->uuid() . '.jpg',
            'ghana_card_back' => 'vendor-applications/ghana-cards/back-' . $this->faker->uuid() . '.jpg',
            'current_step' => 2,
            'completed_step' => 1,
        ]);
    }

    /**
     * Registered vendor (has business certificate).
     */
    public function registeredVendor(): static
    {
        return $this->state(fn(array $attributes) => [
            'has_business_certificate' => true,
            'current_step' => 3,
            'completed_step' => 2,
        ]);
    }

    /**
     * Unregistered vendor (no business documents).
     */
    public function unregisteredVendor(): static
    {
        return $this->state(fn(array $attributes) => [
            'has_business_certificate' => false,
            'current_step' => 3,
            'completed_step' => 2,
        ]);
    }

    /**
     * With registered vendor documents (Step 3A completed).
     */
    public function withRegisteredDocuments(): static
    {
        return $this->state(fn(array $attributes) => [
            'business_certificate_document' => 'vendor-applications/business-documents/cert-' . $this->faker->uuid() . '.pdf',
            'facebook_handle' => $this->faker->optional()->userName(),
            'instagram_handle' => $this->faker->optional()->userName(),
            'twitter_handle' => $this->faker->optional()->userName(),
            'current_step' => 4,
            'completed_step' => 3,
        ]);
    }

    /**
     * With unregistered vendor documents (Step 3B completed).
     */
    public function withUnregisteredDocuments(): static
    {
        return $this->state(fn(array $attributes) => [
            'selfie_image' => 'vendor-applications/selfies/selfie-' . $this->faker->uuid() . '.jpg',
            'mobile_money_number' => '024' . $this->faker->numberBetween(1000000, 9999999),
            'mobile_money_provider' => $this->faker->randomElement(VendorApplication::getMobileMoneyProviders()),
            'proof_of_business' => 'vendor-applications/proof-of-business/proof-' . $this->faker->uuid() . '.pdf',
            'facebook_handle' => $this->faker->optional()->userName(),
            'instagram_handle' => $this->faker->optional()->userName(),
            'twitter_handle' => $this->faker->optional()->userName(),
            'current_step' => 4,
            'completed_step' => 3,
        ]);
    }

    /**
     * Ready for submission (Step 4 completed).
     */
    public function readyToSubmit(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_step' => 5,
            'completed_step' => 4,
        ]);
    }

    /**
     * Pending review.
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VendorApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Under review.
     */
    public function underReview(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VendorApplication::STATUS_UNDER_REVIEW,
            'submitted_at' => now()->subDays(1),
        ]);
    }

    /**
     * Approved.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VendorApplication::STATUS_APPROVED,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VendorApplication::STATUS_REJECTED,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}
