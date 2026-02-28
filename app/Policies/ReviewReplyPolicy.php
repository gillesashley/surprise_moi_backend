<?php

namespace App\Policies;

use App\Models\ReviewReply;
use App\Models\User;

class ReviewReplyPolicy
{
    public function update(User $user, ReviewReply $reviewReply): bool
    {
        return $user->role === 'vendor' && $reviewReply->vendor_id === $user->id;
    }

    public function delete(User $user, ReviewReply $reviewReply): bool
    {
        return $user->role === 'vendor' && $reviewReply->vendor_id === $user->id;
    }
}
