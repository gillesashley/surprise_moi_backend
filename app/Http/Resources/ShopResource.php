<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ShopResource extends JsonResource
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
            'owner_name' => $this->owner_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo ? Storage::url($this->logo) : null,
            'is_active' => $this->is_active,
            'location' => $this->location,
            'phone' => $this->phone,
            'email' => $this->email,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'products_count' => $this->whenCounted('products'),
            'services_count' => $this->whenCounted('services'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
