<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WawVideo;

class WawVideoPolicy
{
    public function delete(User $user, WawVideo $wawVideo): bool
    {
        return $user->id === $wawVideo->vendor_id;
    }
}
