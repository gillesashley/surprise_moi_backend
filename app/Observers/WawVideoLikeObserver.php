<?php

namespace App\Observers;

use App\Models\WawVideoLike;
use App\Notifications\WawVideoLiked;

class WawVideoLikeObserver
{
    /**
     * Handle the WawVideoLike "created" event.
     */
    public function created(WawVideoLike $like): void
    {
        $video = $like->wawVideo;
        $videoOwner = $video->vendor;

        if ($like->user_id === $videoOwner->id) {
            return;
        }

        $videoOwner->notify(new WawVideoLiked($like->user, $video));
    }
}
