<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        // Explicitly get the other participant based on user role in conversation
        $isCustomer = $currentUser->id === $this->customer_id;

        // Load relationships if not already loaded
        if (! $this->relationLoaded('customer')) {
            $this->load('customer');
        }
        if (! $this->relationLoaded('vendor')) {
            $this->load('vendor');
        }

        // Get the OTHER participant (not the current user)
        $otherParticipant = $isCustomer ? $this->vendor : $this->customer;

        $unreadCount = $this->getUnreadCountFor($currentUser);

        return [
            'id' => $this->id,
            'participant' => [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'avatar' => $otherParticipant->avatar ? url($otherParticipant->avatar) : null,
                'role' => $otherParticipant->role,
                'is_online' => true, // This could be enhanced with presence channels
            ],
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_sender_id' => $this->last_message_sender_id,
            'unread_count' => $unreadCount,
            'is_customer' => $isCustomer,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
