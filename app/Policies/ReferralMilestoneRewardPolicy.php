<?php

namespace App\Policies;

use App\Models\ReferralMilestoneReward;
use App\Models\User;

class ReferralMilestoneRewardPolicy
{
    /**
     * Only admins and super_admins can view milestone rewards.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function view(User $user, ReferralMilestoneReward $reward): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, ReferralMilestoneReward $reward): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
