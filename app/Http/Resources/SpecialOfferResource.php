<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialOfferResource extends JsonResource
{
    /**
     * Customize the JSON encoding options to preserve zero fractions for prices.
     */
    public function jsonOptions(): int
    {
        return JSON_PRESERVE_ZERO_FRACTION;
    }

    /**
     * Create a new resource collection with preserved float precision.
     */
    protected static function newCollection(mixed $resource): AnonymousResourceCollection
    {
        return new class($resource, static::class) extends AnonymousResourceCollection
        {
            public function jsonOptions(): int
            {
                return JSON_PRESERVE_ZERO_FRACTION;
            }
        };
    }

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
                    'thumbnail' => $this->product->thumbnail ? storage_url($this->product->thumbnail) : null,
                    'images' => $this->product->relationLoaded('images')
                        ? $this->product->images->map(fn ($img) => storage_url($img->image_path))
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
