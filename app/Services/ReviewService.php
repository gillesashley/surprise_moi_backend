<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewService
{
    /**
     * @return class-string<Product|Service>
     */
    public function getReviewableClass(string $itemType): string
    {
        return match (strtolower($itemType)) {
            'product' => Product::class,
            'service' => Service::class,
            default => Product::class,
        };
    }

    public function normalizeStoredItemType(string $storedType): ?string
    {
        return match ($storedType) {
            'product', Product::class => 'product',
            'service', Service::class => 'service',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function typeCandidatesForItemType(string $itemType): array
    {
        $modelClass = $this->getReviewableClass($itemType);

        return array_values(array_unique([
            $modelClass,
            (new $modelClass)->getMorphClass(),
        ]));
    }

    public function findReviewable(string $itemType, int $itemId): Product|Service|null
    {
        $modelClass = $this->getReviewableClass($itemType);
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query->find($itemId);
    }

    public function isVendorOwnerOfReview(int $vendorId, Review $review): bool
    {
        $itemType = $this->normalizeStoredItemType((string) ($review->item_type ?? $review->reviewable_type));

        if (! $itemType) {
            return false;
        }

        $modelClass = $this->getReviewableClass($itemType);
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        $itemId = (int) ($review->item_id ?? $review->reviewable_id);

        return $query->whereKey($itemId)
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    public function buildContextKey(
        int $userId,
        string $itemType,
        int $itemId,
        ?int $orderId
    ): string {
        return sprintf(
            'user:%d|type:%s|item:%d|order:%d',
            $userId,
            strtolower($itemType),
            $itemId,
            $orderId ?? 0
        );
    }

    public function isVerifiedPurchase(
        int $userId,
        string $itemType,
        int $itemId,
        ?int $orderId = null
    ): bool {
        $query = $this->eligibleOrdersQuery($userId, $itemType, $itemId);

        if ($orderId !== null) {
            $query->whereKey($orderId);
        }

        return $query->exists();
    }

    /**
     * @param  array<int|string, int|string>  $groupedRatings
     * @return array<string, int>
     */
    public function calculateRatingDistribution(array $groupedRatings): array
    {
        $distribution = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        foreach ($groupedRatings as $rating => $count) {
            $bucket = (int) round((float) $rating);
            $bucket = max(1, min(5, $bucket));
            $distribution[(string) $bucket] += (int) $count;
        }

        return $distribution;
    }

    public function resolveOrderForCreate(
        int $userId,
        string $itemType,
        int $itemId,
        ?int $requestedOrderId
    ): ?Order {
        $eligibleOrdersQuery = $this->eligibleOrdersQuery($userId, $itemType, $itemId);

        if ($requestedOrderId) {
            return $eligibleOrdersQuery
                ->whereKey($requestedOrderId)
                ->first();
        }

        $candidateOrders = $eligibleOrdersQuery
            ->orderByDesc('delivered_at')
            ->orderByDesc('fulfilled_at')
            ->orderByDesc('created_at')
            ->get();

        foreach ($candidateOrders as $candidateOrder) {
            $contextKey = $this->buildContextKey(
                $userId,
                $itemType,
                $itemId,
                $candidateOrder->id
            );

            if (! Review::where('context_key', $contextKey)->exists()) {
                return $candidateOrder;
            }
        }

        return null;
    }

    public function updateReviewableRating(Product|Service $reviewable): void
    {
        $stats = $reviewable->reviews()->selectRaw('
            COUNT(*) as count,
            COALESCE(AVG(rating), 0) as average
        ')->first();

        $reviewable->update([
            'rating' => round((float) $stats->average, 2),
            'reviews_count' => (int) $stats->count,
        ]);
    }

    private function eligibleOrdersQuery(
        int $userId,
        string $itemType,
        int $itemId
    ): Builder {
        $reviewableClass = $this->getReviewableClass($itemType);
        $orderableTypes = array_unique([
            $reviewableClass,
            (new $reviewableClass)->getMorphClass(),
        ]);

        return Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['completed', 'fulfilled', 'delivered'])
            ->whereHas('items', function (Builder $query) use ($orderableTypes, $itemId): void {
                $query->whereIn('orderable_type', $orderableTypes)
                    ->where('orderable_id', $itemId);
            });
    }
}
