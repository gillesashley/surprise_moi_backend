<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleWishlistRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Models\Product;
use App\Models\Service;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get all wishlisted items for the authenticated user.
     *
     * Query Parameters:
     * - type: 'product'|'service'|'all' (default: 'all')
     * - page: int (default: 1)
     * - per_page: int (default: 15, max: 100)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:product,service,all'],
        ]);

        $type = $request->input('type', 'all');
        $userId = $request->user()->id;

        $products = collect();
        $services = collect();

        // Get products from wishlist
        if ($type === 'all' || $type === 'product') {
            $productIds = Wishlist::forUser($userId)
                ->ofType('product')
                ->pluck('item_id');

            if ($productIds->isNotEmpty()) {
                $products = Product::with(['vendor', 'shop', 'category', 'images', 'tags', 'activeOffer'])
                    ->whereIn('id', $productIds)
                    ->get();
            }
        }

        // Get services from wishlist
        if ($type === 'all' || $type === 'service') {
            $serviceIds = Wishlist::forUser($userId)
                ->ofType('service')
                ->pluck('item_id');

            if ($serviceIds->isNotEmpty()) {
                $services = Service::with(['vendor', 'shop'])
                    ->whereIn('id', $serviceIds)
                    ->get();
            }
        }

        // Transform resources with isWishlist = true
        $productResources = $products->map(function ($product) use ($request) {
            $resource = new ProductResource($product);
            $resource->isWishlisted = true;  // Precompute to avoid N+1

            return $resource->toArray($request);
        });

        $serviceResources = $services->map(function ($service) use ($request) {
            $resource = new ServiceResource($service);
            $resource->isWishlisted = true;  // Precompute to avoid N+1

            return $resource->toArray($request);
        });

        $totalCount = Wishlist::forUser($userId);
        if ($type !== 'all') {
            $totalCount = $totalCount->ofType($type);
        }
        $totalCount = $totalCount->count();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $productResources,
                'services' => $serviceResources,
                'total_count' => $totalCount,
            ],
            'message' => 'Wishlist retrieved successfully',
        ]);
    }

    /**
     * Toggle an item in the wishlist (add if not exists, remove if exists).
     */
    public function toggle(ToggleWishlistRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $itemId = $request->input('item_id');
        $itemType = $request->input('item_type');

        // Validate item exists
        $itemExists = $this->validateItemExists($itemType, $itemId);
        if (! $itemExists) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($itemType).' not found.',
            ], 404);
        }

        // Check if item is already in wishlist
        $wishlistItem = Wishlist::forUser($userId)
            ->where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->first();

        if ($wishlistItem) {
            // Remove from wishlist
            $wishlistItem->delete();
            $isWishlisted = false;
            $message = 'Removed from wishlist';
        } else {
            // Add to wishlist
            Wishlist::create([
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
            ]);
            $isWishlisted = true;
            $message = 'Added to wishlist';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_wishlisted' => $isWishlisted,
                'item_id' => $itemId,
                'item_type' => $itemType,
            ],
            'message' => $message,
        ]);
    }

    /**
     * Check if specific items are in user's wishlist.
     *
     * Query Parameters:
     * - item_ids: comma-separated list of item IDs
     * - item_type: 'product' or 'service'
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => ['required', 'string'],
            'item_type' => ['required', 'in:product,service'],
        ]);

        $itemIds = explode(',', $request->input('item_ids'));
        $itemIds = array_filter(array_map('intval', $itemIds));
        $itemType = $request->input('item_type');
        $userId = $request->user()->id;

        if (empty($itemIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid item IDs provided.',
            ], 422);
        }

        // Get wishlisted items
        $wishlistedIds = Wishlist::forUser($userId)
            ->ofType($itemType)
            ->whereIn('item_id', $itemIds)
            ->pluck('item_id')
            ->toArray();

        // Build response array
        $items = collect($itemIds)->map(function ($itemId) use ($wishlistedIds) {
            return [
                'item_id' => $itemId,
                'is_wishlisted' => in_array($itemId, $wishlistedIds),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    /**
     * Clear all items from wishlist.
     *
     * Request Body:
     * - type: 'product'|'service'|'all' (optional, default: 'all')
     */
    public function clear(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:product,service,all'],
        ]);

        $type = $request->input('type', 'all');
        $userId = $request->user()->id;

        $query = Wishlist::forUser($userId);

        if ($type !== 'all') {
            $query->ofType($type);
        }

        $deletedCount = $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist cleared successfully',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ]);
    }

    /**
     * Validate if an item exists in the database.
     */
    protected function validateItemExists(string $itemType, int $itemId): bool
    {
        return match ($itemType) {
            'product' => Product::where('id', $itemId)->exists(),
            'service' => Service::where('id', $itemId)->exists(),
            default => false,
        };
    }
}
