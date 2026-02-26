<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UserTyping Event - Broadcast typing indicator to conversation participants.
 * 
 * Broadcasting:
 * - Uses Laravel Reverb WebSocket server
 * - Implements ShouldBroadcastNow for immediate broadcast (no queue)
 * - Sends to private channel: conversation.{id}
 * 
 * Used to show "User is typing..." indicator in chat UI.
 * Fired when: User types in message input (throttled on frontend)
 * 
 * Frontend receives:
 * - Event: 'user.typing'
 * - Data: user_id, user_name, is_typing (true/false)
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     * 
     * @param int $conversationId The conversation where user is typing
     * @param User $user The user who is typing
     * @param bool $isTyping True when typing starts, false when stops
     */
    public function __construct(
        public int $conversationId,
        public User $user,
        public bool $isTyping = true
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * 
     * Broadcasts to conversation's private channel.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    /**
     * The event's broadcast name.
     * 
     * Frontend listens with:
     * echo.private(`conversation.${id}`).listen('.user.typing', callback)
     */
    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    /**
     * Get the data to broadcast.
     * 
     * Lightweight payload for responsive typing indicators.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'is_typing' => $this->isTyping,
        ];
    }
}
