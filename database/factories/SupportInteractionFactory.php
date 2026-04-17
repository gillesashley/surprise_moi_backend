<?php

namespace Database\Factories;

use App\Models\SupportInteraction;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportInteraction>
 */
class SupportInteractionFactory extends Factory
{
    protected $model = SupportInteraction::class;

    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'channel' => fake()->randomElement(SupportInteraction::CHANNELS),
            'direction' => fake()->randomElement([SupportInteraction::DIRECTION_INBOUND, SupportInteraction::DIRECTION_OUTBOUND]),
            'summary' => fake()->paragraph(),
            'occurred_at' => now(),
            'follow_up_at' => null,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ];
    }

    public function withFollowUp(): self
    {
        return $this->state(fn () => ['follow_up_at' => today()->addDays(2)]);
    }
}
