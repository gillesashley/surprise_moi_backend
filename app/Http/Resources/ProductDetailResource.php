<?php

namespace App\Http\Resources;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    /**
     * Optional precomputed wishlist status to avoid N+1 queries.
     */
    public ?bool $isWishlisted = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'detailed_description' => $this->detailed_description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => (float) $this->price,
            'discount_price' => $this->effective_price < (float) $this->price
                ? $this->effective_price
                : ($this->discount_price ? (float) $this->discount_price : null),
            'discount_percentage' => $this->effective_discount_percentage,
            'active_offer' => $this->whenLoaded('activeOffer', function () {
                if (! $this->activeOffer) {
                    return null;
                }

                return [
                    'id' => $this->activeOffer->id,
                    'discount_percentage' => $this->activeOffer->discount_percentage,
                    'tag' => $this->activeOffer->tag,
                    'starts_at' => $this->activeOffer->starts_at?->toISOString(),
                    'ends_at' => $this->activeOffer->ends_at?->toISOString(),
                ];
            }, null),
            'currency' => $this->currency,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($img) => storage_url($img->image_path));
            }),
            'thumbnail' => $this->thumbnail ? storage_url($this->thumbnail) : null,
            'stock' => $this->stock,
            'is_available' => $this->is_available,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'rating' => (float) $this->rating,
            'reviews_count' => $this->reviews_count,
            'reviews_summary' => [
                '5_star' => 0,
                '4_star' => 0,
                '3_star' => 0,
                '2_star' => 0,
                '1_star' => 0,
            ],
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
            'sizes' => $this->sizes,
            'colors' => $this->colors,
            'variants' => $this->whenLoaded('variants', function () {
                return $this->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => (float) $v->price,
                    'stock' => $v->stock,
                ]);
            }),
            'is_featured' => $this->is_featured,
            'is_wishlist' => $this->isWishlisted ?? $this->checkIsWishlisted($request),
            'related_products' => [],
            'delivery_info' => [
                'free_delivery' => $this->free_delivery,
                'delivery_fee' => $this->delivery_fee ? (float) $this->delivery_fee : 0,
                'estimated_days' => $this->estimated_delivery_days ?? '1-2 days',
            ],
            'return_policy' => $this->return_policy ?? '14 days return policy',
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Check if this product is in the authenticated user's wishlist.
     */
    protected function checkIsWishlisted(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return Wishlist::forUser($user->id)
            ->where('item_type', 'product')
            ->where('item_id', $this->id)
            ->exists();
    }
}
