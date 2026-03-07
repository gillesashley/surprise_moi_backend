<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Message  $message  The message that was sent
     * @param  int  $recipientId  The user ID of the recipient
     */
    public function __construct(
        public Message $message,
        public int $recipientId
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
        return 'MessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        $data = [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
                'avatar' => $sender->avatar ? storage_url($sender->avatar) : null,
                'role' => $sender->role,
                'is_online' => $sender->isOnline(),
            ],
            'body' => $this->message->body,
            'type' => $this->message->type,
            'attachments' => $this->message->attachments,
            'is_read' => false,
            'read_at' => null,
            'is_mine' => false,
            'reply_to_id' => $this->message->reply_to_id,
            'reply_to' => $this->buildReplyTo(),
            'created_at' => $this->message->created_at->toIso8601String(),
            'updated_at' => $this->message->updated_at->toIso8601String(),
        ];

        return $data;
    }

    /**
     * Build the reply_to nested object if present.
     *
     * @return array<string, mixed>|null
     */
    private function buildReplyTo(): ?array
    {
        if (! $this->message->reply_to_id) {
            return null;
        }

        $replyTo = $this->message->replyTo;

        if (! $replyTo) {
            return null;
        }

        $replyTo->loadMissing('sender');

        return [
            'id' => $replyTo->id,
            'sender_id' => $replyTo->sender_id,
            'sender' => [
                'id' => $replyTo->sender->id,
                'name' => $replyTo->sender->name,
                'avatar' => $replyTo->sender->avatar ? storage_url($replyTo->sender->avatar) : null,
            ],
            'body' => $replyTo->body,
            'type' => $replyTo->type,
            'attachments' => $replyTo->attachments,
        ];
    }
}
