<?php

namespace App\Policies;

use App\Models\PayoutRequest;
use App\Models\User;

class PayoutRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isInfluencer()
            || $user->isFieldAgent()
            || $user->isMarketer()
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->id === $payoutRequest->user_id
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isInfluencer() || $user->isFieldAgent() || $user->isMarketer();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PayoutRequest $payoutRequest): bool
    {
        return false; // Payouts cannot be updated once created
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PayoutRequest $payoutRequest): bool
    {
        return false; // Payouts cannot be deleted
    }

    /**
     * Determine whether the user can approve the payout request.
     */
    public function approve(User $user, PayoutRequest $payoutRequest): bool
    {
        return ($user->isAdmin() || $user->isSuperAdmin())
            && $payoutRequest->status === PayoutRequest::STATUS_PENDING;
    }

    /**
     * Determine whether the user can reject the payout request.
     */
    public function reject(User $user, PayoutRequest $payoutRequest): bool
    {
        return ($user->isAdmin() || $user->isSuperAdmin())
            && $payoutRequest->status === PayoutRequest::STATUS_PENDING;
    }

    /**
     * Determine whether the user can cancel their own payout request.
     */
    public function cancel(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->id === $payoutRequest->user_id
            && $payoutRequest->status === PayoutRequest::STATUS_PENDING;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PayoutRequest $payoutRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PayoutRequest $payoutRequest): bool
    {
        return false;
    }
}
