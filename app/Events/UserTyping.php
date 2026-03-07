<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $conversationId  The conversation where user is typing
     * @param  User  $user  The user who is typing
     * @param  int  $recipientId  The user ID of the recipient
     * @param  bool  $isTyping  True when typing starts, false when stops
     */
    public function __construct(
        public int $conversationId,
        public User $user,
        public int $recipientId,
        public bool $isTyping = true
    ) {}

    /**
     * Broadcast to the recipient's private user channel.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->recipientId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'UserTyping';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->user->id,
            'sender_name' => $this->user->name,
        ];
    }
}
