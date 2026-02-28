<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\StoreReviewReplyRequest;
use App\Http\Requests\Api\V1\Review\UpdateReviewReplyRequest;
use App\Http\Resources\ReviewReplyResource;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Services\ReviewService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ReviewReplyController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create or update the vendor reply for a review.
     */
    public function store(
        StoreReviewReplyRequest $request,
        Review $review,
        ReviewService $reviewService
    ): JsonResponse {
        $vendor = $request->user();
        $this->authorize('reply', $review);

        if (! $reviewService->isVendorOwnerOfReview($vendor->id, $review)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 403);
        }

        $existingReply = ReviewReply::withTrashed()
            ->where('review_id', $review->id)
            ->first();

        if ($existingReply) {
            if ($existingReply->trashed()) {
                $existingReply->restore();
            }

            $existingReply->update([
                'vendor_id' => $vendor->id,
                'message' => $request->validated('message'),
            ]);

            $reply = $existingReply;
        } else {
            $reply = ReviewReply::query()->create([
                'review_id' => $review->id,
                'vendor_id' => $vendor->id,
                'message' => $request->validated('message'),
            ]);
        }

        $statusCode = $reply->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'success' => true,
            'message' => $reply->wasRecentlyCreated
                ? 'Reply submitted successfully.'
                : 'Reply updated successfully.',
            'data' => new ReviewReplyResource($reply->load('vendor')),
        ], $statusCode);
    }

    /**
     * List reply for a given review.
     */
    public function index(Review $review): JsonResponse
    {
        $replies = $review->reply()
            ->with('vendor')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Review replies retrieved successfully.',
            'data' => ReviewReplyResource::collection($replies),
        ]);
    }

    /**
     * Update a vendor reply.
     */
    public function update(
        UpdateReviewReplyRequest $request,
        ReviewReply $reviewReply,
        ReviewService $reviewService
    ): JsonResponse {
        $this->authorize('update', $reviewReply);

        $vendor = $request->user();

        if (
            $reviewReply->vendor_id !== $vendor->id
            || ! $reviewService->isVendorOwnerOfReview($vendor->id, $reviewReply->review)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 403);
        }

        $reviewReply->update([
            'message' => $request->validated('message'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply updated successfully.',
            'data' => new ReviewReplyResource($reviewReply->load('vendor')),
        ]);
    }

    /**
     * Delete a vendor reply.
     */
    public function destroy(
        ReviewReply $reviewReply,
        ReviewService $reviewService
    ): JsonResponse {
        $this->authorize('delete', $reviewReply);

        $vendor = request()->user();

        if (
            $reviewReply->vendor_id !== $vendor->id
            || ! $reviewService->isVendorOwnerOfReview($vendor->id, $reviewReply->review)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 403);
        }

        $reviewReply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reply deleted successfully.',
            'data' => null,
        ]);
    }
}
