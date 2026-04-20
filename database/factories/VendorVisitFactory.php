<?php

namespace Database\Factories;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorVisitFactory extends Factory
{
    protected $model = VendorVisit::class;

    public function definition(): array
    {
        return [
            'vendor_user_id' => User::factory()->vendor(),
            'field_agent_user_id' => User::factory()->fieldAgent(),
            'status' => VendorVisitStatus::Draft->value,
            'started_at' => now(),
            'visit_latitude' => $this->faker->latitude(),
            'visit_longitude' => $this->faker->longitude(),
            'escalated' => false,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Passed->value,
            'computed_result' => VendorVisitStatus::Passed->value,
            'submitted_at' => now(),
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
            'badge_issued_at' => now(),
            'badge_expires_at' => now()->addMonths(12),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Failed->value,
            'computed_result' => VendorVisitStatus::Failed->value,
            'submitted_at' => now(),
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Submitted->value,
            'submitted_at' => now(),
            'escalated' => true,
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
        ]);
    }
}
