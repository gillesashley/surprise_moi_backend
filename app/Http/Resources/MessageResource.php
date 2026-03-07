<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar ? storage_url($this->sender->avatar) : null,
                'role' => $this->sender->role,
                'is_online' => $this->sender->isOnline(),
            ],
            'body' => $this->body,
            'type' => $this->type,
            'attachments' => $this->attachments,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'is_mine' => $request->user()?->id === $this->sender_id,
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => $this->when($this->reply_to_id && $this->relationLoaded('replyTo') && $this->replyTo, function () {
                return [
                    'id' => $this->replyTo->id,
                    'sender_id' => $this->replyTo->sender_id,
                    'sender' => [
                        'id' => $this->replyTo->sender->id,
                        'name' => $this->replyTo->sender->name,
                        'avatar' => $this->replyTo->sender->avatar ? storage_url($this->replyTo->sender->avatar) : null,
                    ],
                    'body' => $this->replyTo->body,
                    'type' => $this->replyTo->type,
                    'attachments' => $this->replyTo->attachments,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
