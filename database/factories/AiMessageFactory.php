<?php

namespace Database\Factories;

use App\Models\AiConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiMessage>
 */
class AiMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->paragraph(),
            'type' => 'text',
            'metadata' => null,
        ];
    }

    /**
     * Indicate the message is from the user.
     */
    public function fromUser(): static
    {
        return $this->state(fn () => [
            'role' => 'user',
        ]);
    }

    /**
     * Indicate the message is from the assistant.
     */
    public function fromAssistant(): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
        ]);
    }

    /**
     * Create a greeting message.
     */
    public function greeting(): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
            'type' => 'greeting',
            'content' => "Hi! I'm your gift assistant. Tell me about the person you're shopping for.",
        ]);
    }

    /**
     * Create a suggestions message.
     */
    public function suggestions(): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
            'type' => 'suggestions',
            'metadata' => [
                'analysis' => 'Based on the profile provided.',
                'suggestions' => [],
            ],
        ]);
    }
}
