<?php

namespace App\Services;

use App\Models\PayoutRequest;
use App\Models\ReferralMilestoneReward;
use App\Models\User;
use App\Models\UserPayoutDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralPayoutService
{
    /**
     * Create a referral-milestone payout request.
     *
     * @throws \DomainException on business-rule violations.
     */
    public function create(User $user, UserPayoutDetail $detail): PayoutRequest
    {
        if ($detail->user_id !== $user->id) {
            throw new \DomainException('Payout details do not belong to this user.');
        }
        if (!$detail->is_verified) {
            throw new \DomainException('Payout details are not verified with Paystack.');
        }

        return DB::transaction(function () use ($user, $detail) {
            // Row-level lock to serialize create attempts per user.
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if (!$locked->canRequestReferralPayout()) {
                throw new \DomainException(
                    'You cannot request a payout right now. Either you have not crossed the next milestone, you have no earnings available, or you already have a pending request.'
                );
            }

            $amount = $locked->availableReferralPayoutAmount();
            $threshold = $locked->nextReferralUnlockThreshold();
            $pointsDeducted = (int) round($amount * User::POINTS_PER_CEDI);

            $payout = PayoutRequest::create([
                'user_id' => $locked->id,
                'user_role' => 'customer',
                'request_number' => 'PYT-' . strtoupper(Str::random(8)),
                'source' => PayoutRequest::SOURCE_REFERRAL_MILESTONE,
                'referral_milestone_threshold' => $threshold,
                'points_deducted' => $pointsDeducted,
                'user_payout_detail_id' => $detail->id,
                'amount' => $amount,
                'currency' => 'GHS',
                'payout_method' => PayoutRequest::METHOD_MOBILE_MONEY,
                'mobile_money_number' => $detail->mobile_money_number,
                'mobile_money_provider' => $detail->mobile_money_provider,
                'account_name' => $detail->account_name,
                'status' => PayoutRequest::STATUS_PENDING,
            ]);

            return $payout;
        });
    }

    /**
     * Called when a referral payout transitions to 'paid'. Back-links the matching
     * ReferralMilestoneReward (if any) and marks it fulfilled.
     */
    public function onPayoutMarkedPaid(PayoutRequest $payout): void
    {
        if ($payout->source !== PayoutRequest::SOURCE_REFERRAL_MILESTONE || $payout->status !== PayoutRequest::STATUS_PAID) {
            return;
        }

        ReferralMilestoneReward::query()
            ->where('user_id', $payout->user_id)
            ->where('threshold', $payout->referral_milestone_threshold)
            ->where('status', 'pending')
            ->update([
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
                'payout_request_id' => $payout->id,
                'reward_value' => $payout->amount,
            ]);
    }
}
