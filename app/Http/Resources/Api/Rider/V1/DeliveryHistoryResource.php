<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryHistoryResource extends JsonResource
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
            'status' => $this->status,
            'pickup_address' => $this->pickup_address,
            'dropoff_address' => $this->dropoff_address,
            'delivery_fee' => (float) $this->delivery_fee,
            'distance_km' => $this->distance_km ? (float) $this->distance_km : null,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
