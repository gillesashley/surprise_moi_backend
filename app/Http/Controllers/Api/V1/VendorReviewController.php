<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\VendorReviewIndexRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class VendorReviewController extends Controller
{
    use AuthorizesRequests;

    /**
     * List reviews belonging to the authenticated vendor's products/services.
     */
    public function index(
        VendorReviewIndexRequest $request,
        ReviewService $reviewService
    ): JsonResponse {
        $this->authorize('viewVendorReviews', Review::class);

        $vendor = $request->user();
        $data = $request->validated();

        $query = Review::query()
            ->with(['user', 'reviewable', 'reviewImages'])
            ->withCount('helpfuls')
            ->whereHasMorph(
                'reviewable',
                [Product::class, Service::class],
                function (Builder $query) use ($vendor): void {
                    $query->where('vendor_id', $vendor->id);
                }
            )
            ->latest();

        if (isset($data['rating'])) {
            $query->where('rating', $data['rating']);
        }

        if (array_key_exists('has_images', $data)) {
            if ($data['has_images']) {
                $query->whereHas('reviewImages');
            } else {
                $query->whereDoesntHave('reviewImages');
            }
        }

        if (! empty($data['start_date'])) {
            $query->whereDate('created_at', '>=', $data['start_date']);
        }

        if (! empty($data['end_date'])) {
            $query->whereDate('created_at', '<=', $data['end_date']);
        }

        if (! empty($data['search'])) {
            $search = trim($data['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHasMorph(
                        'reviewable',
                        [Product::class, Service::class],
                        function (Builder $reviewableQuery) use ($search): void {
                            $reviewableQuery->where('name', 'like', "%{$search}%");
                        }
                    );
            });
        }

        $query->withExists([
            'helpfuls as is_helpful_by_me' => function (Builder $query) use ($vendor): void {
                $query->where('user_id', $vendor->id);
            },
        ]);

        $perPage = (int) ($data['per_page'] ?? 15);
        $stats = $this->calculateStats(clone $query, $reviewService);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Vendor reviews retrieved successfully.',
            'data' => [
                'reviews' => ReviewResource::collection($reviews),
                'average_rating' => $stats['average_rating'],
                'total_reviews' => $stats['total_reviews'],
                'rating_distribution' => $stats['rating_distribution'],
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateStats(
        Builder $query,
        ReviewService $reviewService
    ): array {
        $baseQuery = $query->withoutEagerLoads()
            ->getQuery()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset']);

        $statsQuery = Review::query()->setQuery($baseQuery);

        $summary = $statsQuery->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(rating), 0) as average
        ')->first();

        $baseQuery2 = $query->withoutEagerLoads()
            ->getQuery()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset']);

        $groupQuery = Review::query()->setQuery($baseQuery2);

        $grouped = $groupQuery->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'average_rating' => round((float) ($summary->average ?? 0), 2),
            'total_reviews' => (int) ($summary->total ?? 0),
            'rating_distribution' => $reviewService->calculateRatingDistribution($grouped),
        ];
    }
}
