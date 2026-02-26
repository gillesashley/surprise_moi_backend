<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\StoreReviewRequest;
use App\Http\Requests\Api\V1\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display a listing of the user's reviews.
     */
    public function index(Request $request): JsonResponse
    {
        $reviews = auth()->user()
            ->reviews()
            ->with(['reviewable'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Store a newly created review.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolve the reviewable model
        $reviewableClass = $data['reviewable_type'] === 'product'
            ? Product::class
            : Service::class;

        $reviewable = $reviewableClass::find($data['reviewable_id']);

        if (! $reviewable) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($data['reviewable_type']).' not found.',
            ], 404);
        }

        // Check if user already reviewed this item
        $existingReview = Review::where('user_id', auth()->id())
            ->whereIn('reviewable_type', array_unique([
                $reviewable->getMorphClass(),
                $reviewableClass,
            ]))
            ->where('reviewable_id', $data['reviewable_id'])
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this '.$data['reviewable_type'].'.',
            ], 422);
        }

        // Check if this is a verified purchase
        $isVerifiedPurchase = $this->checkVerifiedPurchase(
            auth()->id(),
            $reviewableClass,
            $data['reviewable_id']
        );

        DB::beginTransaction();

        try {
            $review = Review::create([
                'user_id' => auth()->id(),
                'reviewable_type' => $reviewableClass,
                'reviewable_id' => $data['reviewable_id'],
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? '',
                'images' => $data['images'] ?? null,
                'is_verified_purchase' => $isVerifiedPurchase,
            ]);

            // Update the reviewable's rating and review count
            $this->updateReviewableRating($reviewable);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'data' => new ReviewResource($review->load('reviewable')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified review.
     */
    public function show(Review $review): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review->load(['reviewable', 'user'])),
        ]);
    }

    /**
     * Update the specified review.
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $review->update($data);

            // Update the reviewable's rating
            $this->updateReviewableRating($review->reviewable);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully.',
                'data' => new ReviewResource($review->fresh()->load('reviewable')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update review.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified review.
     */
    public function destroy(Review $review): JsonResponse
    {
        // Check ownership
        if ($review->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $reviewable = $review->reviewable;
            $review->delete();

            // Update the reviewable's rating
            $this->updateReviewableRating($reviewable);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviews for a specific product.
     */
    public function productReviews(Request $request, Product $product): JsonResponse
    {
        return $this->getReviewsFor($request, $product);
    }

    /**
     * Get reviews for a specific service.
     */
    public function serviceReviews(Request $request, Service $service): JsonResponse
    {
        return $this->getReviewsFor($request, $service);
    }

    /**
     * Get reviews for a specific vendor.
     */
    public function vendorReviews(Request $request, int $vendorId): JsonResponse
    {
        $query = Review::query()
            ->with(['user', 'reviewable'])
            ->whereHasMorph('reviewable', [Product::class, Service::class], function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            })
            ->orderBy('created_at', 'desc');

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by verified purchases only
        if ($request->boolean('verified_only')) {
            $query->where('is_verified_purchase', true);
        }

        $reviews = $query->paginate($request->get('per_page', 15));

        // Calculate rating statistics
        $stats = $this->calculateVendorRatingStats($vendorId);

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'stats' => $stats,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Get reviews for a reviewable entity.
     */
    private function getReviewsFor(Request $request, Product|Service $reviewable): JsonResponse
    {
        $query = $reviewable->reviews()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by verified purchases only
        if ($request->boolean('verified_only')) {
            $query->where('is_verified_purchase', true);
        }

        $reviews = $query->paginate($request->get('per_page', 15));

        // Calculate rating statistics
        $stats = $this->calculateRatingStats($reviewable);

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'stats' => $stats,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Check if the user has purchased this product/service.
     */
    private function checkVerifiedPurchase(int $userId, string $reviewableType, int $reviewableId): bool
    {
        return Order::where('user_id', $userId)
            ->where('status', 'delivered')
            ->whereHas('items', function ($query) use ($reviewableType, $reviewableId) {
                $query->where('orderable_type', $reviewableType)
                    ->where('orderable_id', $reviewableId);
            })
            ->exists();
    }

    /**
     * Update the rating and review count for a reviewable entity.
     */
    private function updateReviewableRating(Product|Service $reviewable): void
    {
        $stats = $reviewable->reviews()->selectRaw('
            COUNT(*) as count,
            COALESCE(AVG(rating), 0) as average
        ')->first();

        $reviewable->update([
            'rating' => round($stats->average, 2),
            'reviews_count' => $stats->count,
        ]);
    }

    /**
     * Calculate rating statistics for a reviewable entity.
     *
     * @return array<string, mixed>
     */
    private function calculateRatingStats(Product|Service $reviewable): array
    {
        $reviews = $reviewable->reviews();

        $distribution = $reviews->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all ratings 1-5 are present
        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = $distribution[$i] ?? 0;
        }

        return [
            'average_rating' => $reviewable->rating,
            'total_reviews' => $reviewable->reviews_count,
            'verified_purchases' => $reviews->where('is_verified_purchase', true)->count(),
            'rating_distribution' => $ratingDistribution,
        ];
    }

    /**
     * Calculate rating statistics for a vendor across all their products/services.
     *
     * @return array<string, mixed>
     */
    private function calculateVendorRatingStats(int $vendorId): array
    {
        $query = Review::query()
            ->whereHasMorph('reviewable', [Product::class, Service::class], function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            });

        $stats = (clone $query)->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(rating), 0) as average,
            SUM(CASE WHEN is_verified_purchase = true THEN 1 ELSE 0 END) as verified
        ')->first();

        $distribution = (clone $query)->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all ratings 1-5 are present
        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = $distribution[$i] ?? 0;
        }

        return [
            'average_rating' => round($stats->average, 2),
            'total_reviews' => $stats->total,
            'verified_purchases' => $stats->verified,
            'rating_distribution' => $ratingDistribution,
        ];
    }
}
