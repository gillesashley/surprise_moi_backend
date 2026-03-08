<?php

namespace App\Observers;

use App\Models\Review;
use App\Notifications\NewReview;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $reviewable = $review->reviewable;

        if (! $reviewable) {
            return;
        }

        // Get the vendor who owns the reviewed item (product or service).
        // Both Product and Service have a vendor() relationship.
        $vendor = $reviewable->vendor ?? null;

        if (! $vendor || $review->user_id === $vendor->id) {
            return;
        }

        $vendor->notify(new NewReview($review->user, $review));
    }
}
