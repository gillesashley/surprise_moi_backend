<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPayoutDetail;

class UserPayoutDetailPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserPayoutDetail $detail): bool
    {
        return $user->id === $detail->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserPayoutDetail $detail): bool
    {
        return $user->id === $detail->user_id;
    }

    public function delete(User $user, UserPayoutDetail $detail): bool
    {
        return $user->id === $detail->user_id;
    }
}
