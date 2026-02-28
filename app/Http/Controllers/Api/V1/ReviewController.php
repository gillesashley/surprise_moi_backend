<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\ListItemReviewsRequest;
use App\Http\Requests\Api\V1\Review\StoreReviewRequest;
use App\Http\Requests\Api\V1\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of reviews for an item.
     */
    public function index(
        ListItemReviewsRequest $request,
        ReviewService $reviewService
    ): JsonResponse {
        $data = $request->validated();
        $itemType = $data['item_type'];
        $itemId = (int) $data['item_id'];

        $reviewable = $reviewService->findReviewable($itemType, $itemId);
        if (! $reviewable) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($itemType).' not found.',
                'data' => null,
            ], 404);
        }

        $query = Review::query()
            ->with([
                'user',
                'reviewable',
                'reviewImages',
            ])
            ->withCount('helpfuls')
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->latest();

        $this->withViewerHelpfulState($query, $request->user());

        $reviews = $query->paginate((int) ($data['per_page'] ?? 15));
        $stats = $this->calculateRatingStats($reviewable, $reviewService);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully.',
            'data' => $this->buildListPayload($reviews, $stats),
        ]);
    }

    /**
     * Store a newly created review.
     */
    public function store(
        StoreReviewRequest $request,
        ReviewService $reviewService
    ): JsonResponse {
        $data = $request->validated();
        $itemType = $data['item_type'];
        $itemId = (int) $data['item_id'];
        $user = $request->user();

        $reviewable = $reviewService->findReviewable($itemType, $itemId);
        if (! $reviewable) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($itemType).' not found.',
                'data' => null,
            ], 404);
        }

        $order = $reviewService->resolveOrderForCreate(
            $user->id,
            $itemType,
            $itemId,
            isset($data['order_id']) ? (int) $data['order_id'] : null
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review purchased items from completed orders.',
                'data' => null,
            ], 422);
        }

        $contextKey = $reviewService->buildContextKey(
            $user->id,
            $itemType,
            $itemId,
            $order->id
        );

        if (Review::query()->where('context_key', $contextKey)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this purchase context.',
                'data' => null,
            ], 422);
        }

        $storedImagePaths = $this->storeUploadedImages($request);

        DB::beginTransaction();

        try {
            $review = Review::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'reviewable_type' => $itemType,
                'reviewable_id' => $itemId,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'images' => $storedImagePaths,
                'is_verified_purchase' => true,
                'context_key' => $contextKey,
                'helpful_count' => 0,
            ]);

            $this->replaceReviewImages($review, $storedImagePaths);
            $reviewService->updateReviewableRating($reviewable);

            DB::commit();

            $review = $review->fresh()
                ->load(['user', 'reviewable', 'reviewImages'])
                ->loadCount('helpfuls');
            $review->setAttribute('is_helpful_by_me', false);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'data' => [
                    'review' => new ReviewResource($review),
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteStoredImages($storedImagePaths);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display the specified review.
     */
    public function show(Request $request, Review $review): JsonResponse
    {
        $review->load(['user', 'reviewable', 'reviewImages'])
            ->loadCount('helpfuls');

        $isHelpfulByMe = false;
        if ($request->user()) {
            $isHelpfulByMe = $review->helpfuls()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        $review->setAttribute('is_helpful_by_me', $isHelpfulByMe);

        return response()->json([
            'success' => true,
            'message' => 'Review retrieved successfully.',
            'data' => new ReviewResource($review),
        ]);
    }

    /**
     * Update the specified review.
     */
    public function update(
        UpdateReviewRequest $request,
        Review $review,
        ReviewService $reviewService
    ): JsonResponse {
        $this->authorize('update', $review);

        $data = $request->validated();
        $itemType = $data['item_type'];
        $itemId = (int) $data['item_id'];

        if (
            $review->item_type !== $itemType
            || (int) $review->item_id !== $itemId
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Item context does not match this review.',
                'data' => null,
            ], 422);
        }

        if (
            isset($data['order_id'])
            && $review->order_id
            && (int) $data['order_id'] !== (int) $review->order_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Item context does not match this review.',
                'data' => null,
            ], 422);
        }

        $newImagePaths = null;
        if ($request->hasFile('images')) {
            $newImagePaths = $this->storeUploadedImages($request);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ];

            $oldImagePaths = $review->reviewImages->pluck('storage_path')->all();
            if (is_array($review->images)) {
                $oldImagePaths = array_unique(array_merge($oldImagePaths, $review->images));
            }

            if ($newImagePaths !== null) {
                $updateData['images'] = $newImagePaths;
            }

            $review->update($updateData);

            if ($newImagePaths !== null) {
                $this->replaceReviewImages($review, $newImagePaths);
            }

            if ($review->reviewable) {
                $reviewService->updateReviewableRating($review->reviewable);
            }

            DB::commit();

            if ($newImagePaths !== null) {
                $this->deleteStoredImages($oldImagePaths);
            }

            $freshReview = $review->fresh()
                ->load(['user', 'reviewable', 'reviewImages'])
                ->loadCount('helpfuls');

            $isHelpfulByMe = $request->user()
                ? $freshReview->helpfuls()->where('user_id', $request->user()->id)->exists()
                : false;
            $freshReview->setAttribute('is_helpful_by_me', $isHelpfulByMe);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully.',
                'data' => [
                    'review' => new ReviewResource($freshReview),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($newImagePaths !== null) {
                $this->deleteStoredImages($newImagePaths);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update review.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified review.
     */
    public function destroy(
        Request $request,
        Review $review,
        ReviewService $reviewService
    ): JsonResponse {
        $this->authorize('delete', $review);

        DB::beginTransaction();

        try {
            $reviewable = $review->reviewable;
            $paths = $review->reviewImages->pluck('storage_path')->all();
            if (is_array($review->images)) {
                $paths = array_unique(array_merge($paths, $review->images));
            }

            $review->delete();

            if ($reviewable) {
                $reviewService->updateReviewableRating($reviewable);
            }

            DB::commit();

            $this->deleteStoredImages($paths);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review.',
                'data' => null,
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
            ->with(['user', 'reviewable', 'reviewImages'])
            ->withCount('helpfuls')
            ->whereHasMorph('reviewable', [Product::class, Service::class], function (Builder $query) use ($vendorId): void {
                $query->where('vendor_id', $vendorId);
            })
            ->latest();

        if ($request->has('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified_purchase', true);
        }

        $this->withViewerHelpfulState($query, $request->user());

        $reviews = $query->paginate((int) $request->get('per_page', 15));

        $reviewService = app(ReviewService::class);
        $stats = $this->calculateVendorRatingStats($vendorId, $reviewService);

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
     * Get reviews for a reviewable entity.
     */
    private function getReviewsFor(Request $request, Product|Service $reviewable): JsonResponse
    {
        $query = $reviewable->reviews()
            ->with(['user', 'reviewImages'])
            ->withCount('helpfuls')
            ->latest();

        if ($request->has('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified_purchase', true);
        }

        $this->withViewerHelpfulState($query, $request->user());

        $reviews = $query->paginate((int) $request->get('per_page', 15));
        $reviewService = app(ReviewService::class);
        $stats = $this->calculateRatingStats($reviewable, $reviewService);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully.',
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
    private function calculateRatingStats(
        Product|Service $reviewable,
        ReviewService $reviewService
    ): array {
        $reviewsQuery = $reviewable->reviews();

        $summary = (clone $reviewsQuery)->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(rating), 0) as average
        ')->first();

        $grouped = (clone $reviewsQuery)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'average_rating' => round((float) ($summary->average ?? 0), 2),
            'total_reviews' => (int) ($summary->total ?? 0),
            'rating_distribution' => $reviewService->calculateRatingDistribution($grouped),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateVendorRatingStats(
        int $vendorId,
        ReviewService $reviewService
    ): array {
        $query = Review::query()
            ->whereHasMorph('reviewable', [Product::class, Service::class], function (Builder $query) use ($vendorId): void {
                $query->where('vendor_id', $vendorId);
            });

        $stats = (clone $query)->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(rating), 0) as average
        ')->first();

        $grouped = (clone $query)->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'average_rating' => round((float) ($stats->average ?? 0), 2),
            'total_reviews' => (int) ($stats->total ?? 0),
            'rating_distribution' => $reviewService->calculateRatingDistribution($grouped),
        ];
    }

    private function withViewerHelpfulState(Builder $query, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $query->withExists([
            'helpfuls as is_helpful_by_me' => function (Builder $query) use ($viewer): void {
                $query->where('user_id', $viewer->id);
            },
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function storeUploadedImages(Request $request): array
    {
        if (! $request->hasFile('images')) {
            return [];
        }

        $paths = [];
        foreach ($request->file('images') as $image) {
            $paths[] = $image->store('reviews/images');
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function replaceReviewImages(Review $review, array $paths): void
    {
        $review->reviewImages()->delete();

        foreach (array_values($paths) as $index => $path) {
            $review->reviewImages()->create([
                'storage_path' => $path,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredImages(array $paths): void
    {
        if (empty($paths)) {
            return;
        }

        Storage::disk()->delete($paths);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function buildListPayload(
        LengthAwarePaginator $reviews,
        array $stats
    ): array {
        return [
            'reviews' => ReviewResource::collection($reviews),
            'average_rating' => $stats['average_rating'],
            'total_reviews' => $stats['total_reviews'],
            'rating_distribution' => $stats['rating_distribution'],
            'current_page' => $reviews->currentPage(),
            'last_page' => $reviews->lastPage(),
        ];
    }
}
