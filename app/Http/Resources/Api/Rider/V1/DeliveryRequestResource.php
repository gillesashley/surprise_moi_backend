<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRequestResource extends JsonResource
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
            'order_id' => (int) $this->order_id,
            'status' => $this->status,
            'pickup_address' => $this->pickup_address,
            'pickup_latitude' => (float) $this->pickup_latitude,
            'pickup_longitude' => (float) $this->pickup_longitude,
            'dropoff_address' => $this->dropoff_address,
            'dropoff_latitude' => (float) $this->dropoff_latitude,
            'dropoff_longitude' => (float) $this->dropoff_longitude,
            'delivery_fee' => (float) $this->delivery_fee,
            'distance_km' => $this->distance_km ? (float) $this->distance_km : null,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'picked_up_at' => $this->picked_up_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'order' => $this->whenLoaded('order', fn () => [
                'order_number' => $this->order->order_number,
                'receiver_name' => $this->order->receiver_name,
                'receiver_phone' => $this->order->receiver_phone,
                'delivery_pin' => $this->when($this->isActive(), $this->order->delivery_pin),
                'special_instructions' => $this->order->special_instructions,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => (int) $this->vendor->id,
                'name' => $this->vendor->name,
                'phone' => $this->vendor->phone,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
