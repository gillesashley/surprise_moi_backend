<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_category' => $this->vehicle_category,
            'license_plate' => $this->license_plate,
            'status' => $this->status,
            'is_online' => (bool) $this->is_online,
            'average_rating' => (float) $this->average_rating,
            'total_deliveries' => (int) $this->total_deliveries,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
