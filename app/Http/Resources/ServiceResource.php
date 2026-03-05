<?php

namespace App\Http\Resources;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
            'service_type' => $this->service_type,
            'charge_start' => (float) $this->charge_start,
            'charge_end' => $this->charge_end ? (float) $this->charge_end : null,
            'currency' => $this->currency,
            'images' => $this->thumbnail ? [storage_url($this->thumbnail)] : [],
            'thumbnail' => $this->thumbnail ? storage_url($this->thumbnail) : null,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'rating' => (float) $this->rating,
            'reviews_count' => $this->reviews_count,
            'availability' => $this->availability,
            'is_wishlist' => $this->isWishlisted ?? $this->checkIsWishlisted($request),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Cached wishlist service IDs per user for the current request.
     *
     * @var array<int, array<int, int>>
     */
    protected static array $wishlistServiceIds = [];

    /**
     * Check if this service is in the authenticated user's wishlist.
     * Only called if isWishlisted is not precomputed.
     */
    protected function checkIsWishlisted(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        $userId = $user->id;

        if (! array_key_exists($userId, self::$wishlistServiceIds)) {
            self::$wishlistServiceIds[$userId] = Wishlist::forUser($userId)
                ->where('item_type', 'service')
                ->pluck('item_id')
                ->all();
        }

        return in_array($this->id, self::$wishlistServiceIds[$userId], true);
    }

    /**
     * Flush the static wishlist cache.
     * Called on each request by Octane's RequestReceived listener.
     */
    public static function flushWishlistCache(): void
    {
        self::$wishlistServiceIds = [];
    }
}
