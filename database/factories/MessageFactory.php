<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'type' => 'text',
            'attachments' => null,
            'read_at' => null,
        ];
    }

    /**
     * Set the conversation for the message.
     */
    public function forConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Set the sender for the message.
     */
    public function fromSender(User $sender): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $sender->id,
        ]);
    }

    /**
     * Mark the message as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the message as unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Create an image message.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'attachments' => [
                [
                    'path' => 'chat-attachments/'.$this->faker->uuid().'.jpg',
                    'url' => '/storage/chat-attachments/'.$this->faker->uuid().'.jpg',
                    'name' => 'image.jpg',
                    'size' => $this->faker->numberBetween(10000, 5000000),
                    'mime_type' => 'image/jpeg',
                ],
            ],
        ]);
    }

    /**
     * Create a file message.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'attachments' => [
                [
                    'path' => 'chat-attachments/'.$this->faker->uuid().'.pdf',
                    'url' => '/storage/chat-attachments/'.$this->faker->uuid().'.pdf',
                    'name' => 'document.pdf',
                    'size' => $this->faker->numberBetween(10000, 10000000),
                    'mime_type' => 'application/pdf',
                ],
            ],
        ]);
    }

    /**
     * Create a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'body' => $this->faker->sentence(),
        ]);
    }
}
