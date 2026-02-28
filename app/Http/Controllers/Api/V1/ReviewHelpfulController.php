<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewHelpfulController extends Controller
{
    /**
     * Toggle helpful reaction for a review.
     */
    public function toggle(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();
        $isHelpfulByMe = false;

        DB::transaction(function () use ($review, $user, &$isHelpfulByMe): void {
            $existing = $review->helpfuls()
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $isHelpfulByMe = false;

                return;
            }

            $review->helpfuls()->create([
                'user_id' => $user->id,
            ]);

            $isHelpfulByMe = true;
        });

        $helpfulCount = $review->helpfuls()->count();
        $review->update(['helpful_count' => $helpfulCount]);

        return response()->json([
            'success' => true,
            'message' => $isHelpfulByMe
                ? 'Review marked as helpful.'
                : 'Helpful reaction removed.',
            'data' => [
                'review_id' => $review->id,
                'is_helpful_by_me' => $isHelpfulByMe,
                'helpful_count' => $helpfulCount,
            ],
        ]);
    }
}
