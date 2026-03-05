<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SpecialOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_percentage' => $this->discount_percentage,
            'tag' => $this->tag,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'is_active' => $this->is_active,
            'product' => $this->when($this->relationLoaded('product'), function () {
                $price = (float) $this->product->price;
                $discountedPrice = round($price * (1 - $this->discount_percentage / 100), 2);

                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'price' => $price,
                    'discounted_price' => $discountedPrice,
                    'thumbnail' => $this->product->thumbnail ? Storage::url($this->product->thumbnail) : null,
                    'images' => $this->product->relationLoaded('images')
                        ? $this->product->images->map(fn ($img) => Storage::url($img->image_path))
                        : [],
                    'shop' => $this->product->relationLoaded('shop') ? [
                        'id' => $this->product->shop->id,
                        'name' => $this->product->shop->name,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
