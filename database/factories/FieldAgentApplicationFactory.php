<?php

namespace Database\Factories;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\City;
use App\Models\FieldAgentApplication;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<FieldAgentApplication> */
class FieldAgentApplicationFactory extends Factory
{
    protected $model = FieldAgentApplication::class;

    public function definition(): array
    {
        $region = Region::inRandomOrder()->first() ?? Region::factory()->create();
        $city = $region->cities()->inRandomOrder()->first() ?? City::factory()->create(['region_id' => $region->id]);

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'contact_number' => '+233'.fake()->numerify('#########'),
            'region_id' => $region->id,
            'city_id' => $city->id,
            'location' => fake()->streetName(),
            'ghana_card_number' => 'GHA-'.fake()->unique()->numerify('#########').'-'.fake()->numberBetween(0, 9),
            'ghana_card_image_path' => 'field-agents/ghana-cards/sample-front.jpg',
            'ghana_card_back_image_path' => 'field-agents/ghana-cards/sample-back.jpg',
            'selfie_path' => 'field-agents/selfies/sample.jpg',
            'password' => Hash::make('password123'),
            'status' => FieldAgentApplicationStatus::Pending->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => FieldAgentApplicationStatus::Pending->value]);
    }

    public function underReview(): static
    {
        return $this->state(['status' => FieldAgentApplicationStatus::UnderReview->value]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => FieldAgentApplicationStatus::Approved->value,
            'reviewed_at' => now(),
            'password' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => FieldAgentApplicationStatus::Rejected->value,
            'reviewed_at' => now(),
            'rejection_reason' => 'Documents unclear. Please resubmit.',
        ]);
    }
}
