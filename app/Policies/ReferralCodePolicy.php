<?php

namespace App\Policies;

use App\Models\ReferralCode;
use App\Models\User;

class ReferralCodePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isInfluencer() || $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReferralCode $referralCode): bool
    {
        return $user->id === $referralCode->influencer_id
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isInfluencer() || $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReferralCode $referralCode): bool
    {
        return $user->id === $referralCode->influencer_id
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReferralCode $referralCode): bool
    {
        return $user->id === $referralCode->influencer_id
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReferralCode $referralCode): bool
    {
        return $user->id === $referralCode->influencer_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReferralCode $referralCode): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
