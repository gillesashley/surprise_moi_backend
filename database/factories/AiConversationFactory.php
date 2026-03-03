<?php

namespace Database\Factories;

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiConversation>
 */
class AiConversationFactory extends Factory
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
            'partner_profile_id' => null,
            'title' => fake()->sentence(4),
            'profile_summary' => null,
            'status' => 'active',
            'agent_conversation_id' => null,
        ];
    }

    /**
     * Indicate the conversation has a partner profile.
     */
    public function withPartnerProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'partner_profile_id' => PartnerProfile::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
        ]);
    }

    /**
     * Indicate the conversation is archived.
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
        ]);
    }
}
