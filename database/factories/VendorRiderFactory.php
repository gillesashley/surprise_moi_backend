<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\User;
use App\Models\VendorRider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorRider>
 */
class VendorRiderFactory extends Factory
{
    protected $model = VendorRider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->vendor(),
            'rider_id' => Rider::factory(),
            'nickname' => fake()->optional(0.5)->firstName(),
            'is_default' => false,
        ];
    }
}
