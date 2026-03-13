<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
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
            'name' => $this->name,
            'shop_name' => $this->name,
            'profile_image' => $this->avatar ? storage_url($this->avatar) : null,
            'banner' => $this->banner ? storage_url($this->banner) : null,
            'location' => $this->bio,
            'products_rating' => $this->whenNotNull($this->products_avg_rating !== null ? (float) $this->products_avg_rating : null),
            'services_rating' => $this->whenNotNull($this->services_avg_rating !== null ? (float) $this->services_avg_rating : null),
            'is_verified' => $this->email_verified_at !== null,
            'is_online' => true,
            'is_popular' => (bool) ($this->is_popular ?? false),
            'products_count' => (int) ($this->products_count ?? 0),
            'services_count' => (int) ($this->services_count ?? 0),
            'completed_orders_count' => (int) ($this->completed_orders_count ?? 0),
            'response_time' => 'Within 2 hours',
            'delivery_time' => 'Same day delivery',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
