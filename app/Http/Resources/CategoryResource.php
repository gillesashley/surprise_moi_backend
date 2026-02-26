<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            'icon' => $this->icon ? asset($this->icon) : null,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'products_count' => $this->whenCounted('products'),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
