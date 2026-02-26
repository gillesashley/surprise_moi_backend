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
            'name' => $this->name,
            'description' => $this->description,
            'detailed_description' => $this->detailed_description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'discount_percentage' => $this->discount_percentage,
            'currency' => $this->currency,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($img) => url($img->image_path));
            }),
            'thumbnail' => $this->thumbnail ? url($this->thumbnail) : null,
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
     * Cached wishlist product IDs per user for the current request.
     *
     * @var array<int, array<int, int>>
     */
    protected static array $wishlistProductIds = [];

    /**
     * Check if this product is in the authenticated user's wishlist.
     * Only called if isWishlisted is not precomputed.
     */
    protected function checkIsWishlisted(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        $userId = $user->id;

        if (! array_key_exists($userId, self::$wishlistProductIds)) {
            self::$wishlistProductIds[$userId] = Wishlist::forUser($userId)
                ->where('item_type', 'product')
                ->pluck('item_id')
                ->all();
        }

        return in_array($this->id, self::$wishlistProductIds[$userId], true);
    }
}
