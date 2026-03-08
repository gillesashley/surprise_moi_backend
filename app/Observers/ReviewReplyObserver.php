<?php

namespace App\Observers;

use App\Models\ReviewReply;
use App\Notifications\ReviewReplied;

class ReviewReplyObserver
{
    /**
     * Handle the ReviewReply "created" event.
     */
    public function created(ReviewReply $reply): void
    {
        $review = $reply->review;
        $reviewAuthor = $review->user;

        if ($reply->vendor_id === $reviewAuthor->id) {
            return;
        }

        $reviewAuthor->notify(new ReviewReplied($reply->vendor, $reply));
    }
}
