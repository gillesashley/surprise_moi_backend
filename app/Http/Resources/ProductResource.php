<?php

namespace App\Http\Resources;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'discount_percentage' => $this->discount_percentage,
            'currency' => $this->currency,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($img) => storage_url($img->image_path));
            }),
            'thumbnail' => $this->thumbnail ? storage_url($this->thumbnail) : null,
            'stock' => $this->stock,
            'is_available' => $this->is_available,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'rating' => (float) $this->rating,
            'reviews_count' => $this->reviews_count,
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
            'sizes' => $this->sizes,
            'colors' => $this->colors,
            'is_featured' => $this->is_featured,
            'is_wishlist' => $this->isWishlisted ?? $this->checkIsWishlisted($request),
            'created_at' => $this->created_at?->toISOString(),
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

    /**
     * Flush the static wishlist cache.
     * Called on each request by Octane's RequestReceived listener.
     */
    public static function flushWishlistCache(): void
    {
        self::$wishlistProductIds = [];
    }
}
