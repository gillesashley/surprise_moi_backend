<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ConversationDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        // Explicitly determine if current user is the customer
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
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'avatar' => $this->customer->avatar ? Storage::url($this->customer->avatar) : null,
                'role' => $this->customer->role,
            ],
            'vendor' => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'avatar' => $this->vendor->avatar ? Storage::url($this->vendor->avatar) : null,
                'role' => $this->vendor->role,
            ],
            'participant' => [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'avatar' => $otherParticipant->avatar ? Storage::url($otherParticipant->avatar) : null,
                'role' => $otherParticipant->role,
                'is_online' => true,
            ],
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_sender_id' => $this->last_message_sender_id,
            'unread_count' => $unreadCount,
            'is_customer' => $isCustomer,
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
