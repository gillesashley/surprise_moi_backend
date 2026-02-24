<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            'icon' => $this->icon ? asset($this->icon) : null,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'products_count' => $this->whenCounted('products'),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
