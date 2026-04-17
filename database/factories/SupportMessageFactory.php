<?php

namespace Database\Factories;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    public function definition(): array
    {
        return [
            'to_user_id' => null,
            'to_phone' => '+233'.fake()->numerify('#########'),
            'body' => fake()->sentence(),
            'template_key' => null,
            'status' => SupportMessage::STATUS_QUEUED,
            'sent_by' => User::factory()->create(['role' => 'admin'])->id,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn () => [
            'status' => SupportMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => SupportMessage::STATUS_FAILED,
            'failed_reason' => 'Provider unavailable',
        ]);
    }
}
