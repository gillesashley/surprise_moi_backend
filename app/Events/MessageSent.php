<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessageSent Event - Broadcast new chat message to conversation participants.
 *
 * Broadcasting:
 * - Uses Laravel Reverb WebSocket server
 * - Sends to private channel: conversation.{id}
 * - Only participants can subscribe (see routes/channels.php)
 *
 * Implements ShouldBroadcast to queue the broadcast (async).
 * Fired when: User sends a message via ChatController
 *
 * Frontend receives:
 * - Event: 'message.sent'
 * - Data: Message with sender info
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Message  $message  The message that was sent
     */
    public function __construct(public Message $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts to private channel scoped to conversation.
     * Authorization checked in routes/channels.php
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * Frontend listens with:
     * echo.private(`conversation.${id}`).listen('.message.sent', callback)
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * Includes message content and sender info.
     * Keeps payload minimal for WebSocket efficiency.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
                'avatar' => $this->message->sender->avatar,
            ],
            'body' => $this->message->body,
            'type' => $this->message->type,
            'attachments' => $this->message->attachments,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
