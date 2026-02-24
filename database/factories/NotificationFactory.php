<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([
                'vendor_submitted',
                'vendor_approved',
                'vendor_rejected',
                'chat_message',
                'system',
            ]),
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->sentence(),
            'data' => [],
            'user_id' => User::factory(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    public function vendorSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vendor_submitted',
            'title' => 'New Vendor Application',
        ]);
    }

    public function vendorApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vendor_approved',
            'title' => 'Vendor Approved',
        ]);
    }

    public function vendorRejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vendor_rejected',
            'title' => 'Vendor Rejected',
        ]);
    }

    public function chatMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'chat_message',
            'title' => 'New Message',
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'title' => 'System Notification',
        ]);
    }
}
