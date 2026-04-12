<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralMilestoneReward;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only referral summary for the authenticated mobile user.
 *
 * Exposes the caller's personal referral code, current points total,
 * configured milestone thresholds, and progress toward the next milestone.
 * Accessible by any authenticated user (any role) — not restricted to the
 * influencer-only endpoints under /referral-codes.
 *
 * First-call side effect: if the user has no ReferralCode row yet, one is
 * created via ReferralService so the code is immediately shareable.
 */
class MyReferralController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get or create the user's personal referral code. Each user keeps
        // a single active code here; historical inactive codes are ignored.
        $referralCode = ReferralCode::where('influencer_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $referralCode) {
            $referralCode = $this->referralService->createReferralCode(
                influencer: $user,
                description: 'Personal referral code',
            );
        }

        $points = (int) ($user->referral_points ?? 0);
        $first = (int) config('referral.milestone_first', 1000);
        $increment = (int) config('referral.milestone_increment', 5000);

        // Build the milestone ladder. Always show at least the first 6 steps,
        // and make sure we extend far enough to include the user's current
        // points plus the next three upcoming thresholds. Safety-capped at 30
        // rows so a misconfigured increment cannot produce an unbounded list.
        $thresholds = [$first];
        if ($increment > 0) {
            $t = $increment;
            while (count($thresholds) < 30) {
                if ($t !== $first) {
                    $thresholds[] = $t;
                }
                $haveEnoughRows = count($thresholds) >= 6;
                $coveredUpcoming = $t >= $points + (3 * $increment);
                if ($haveEnoughRows && $coveredUpcoming) {
                    break;
                }
                $t += $increment;
            }
        }

        // Index existing milestone reward rows by threshold so we can attach
        // fulfillment status to any ladder step the user has already crossed.
        $rewards = ReferralMilestoneReward::where('user_id', $user->id)
            ->get()
            ->keyBy('threshold');

        $milestones = [];
        foreach ($thresholds as $threshold) {
            $reward = $rewards->get($threshold);

            if ($reward) {
                $milestones[] = [
                    'threshold' => $threshold,
                    'status' => $reward->status === ReferralMilestoneReward::STATUS_FULFILLED
                        ? 'fulfilled'
                        : 'earned',
                    'points_at_milestone' => (int) $reward->points_at_milestone,
                    'achieved_at' => $reward->created_at?->toIso8601String(),
                    'fulfilled_at' => $reward->fulfilled_at?->toIso8601String(),
                    'reward_description' => $reward->reward_description,
                ];
            } elseif ($points >= $threshold) {
                // Defensive path: points crossed the threshold but the reward
                // row is missing (possible on very old data predating the
                // milestone feature). Report as earned so the UI stays truthful.
                $milestones[] = [
                    'threshold' => $threshold,
                    'status' => 'earned',
                    'points_at_milestone' => $points,
                    'achieved_at' => null,
                    'fulfilled_at' => null,
                    'reward_description' => null,
                ];
            } else {
                $milestones[] = [
                    'threshold' => $threshold,
                    'status' => 'upcoming',
                    'points_at_milestone' => null,
                    'achieved_at' => null,
                    'fulfilled_at' => null,
                    'reward_description' => null,
                ];
            }
        }

        // Next milestone = smallest threshold strictly greater than points.
        // Previous milestone = largest threshold <= points (0 if none yet).
        $nextMilestone = null;
        $previousMilestone = 0;
        foreach ($thresholds as $threshold) {
            if ($threshold <= $points) {
                $previousMilestone = $threshold;
            } elseif ($nextMilestone === null) {
                $nextMilestone = $threshold;
            }
        }

        $progressToNext = 1.0;
        if ($nextMilestone !== null) {
            $span = $nextMilestone - $previousMilestone;
            $progressToNext = $span > 0
                ? max(0.0, min(1.0, ($points - $previousMilestone) / $span))
                : 0.0;
        }

        $totalReferrals = Referral::where('influencer_id', $user->id)
            ->whereIn('status', [Referral::STATUS_PENDING, Referral::STATUS_ACTIVE])
            ->count();

        $pendingRewards = ReferralMilestoneReward::where('user_id', $user->id)
            ->where('status', ReferralMilestoneReward::STATUS_PENDING)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $referralCode->code,
                'referral_points' => $points,
                'next_milestone' => $nextMilestone,
                'previous_milestone' => $previousMilestone,
                'progress_to_next' => (float) round($progressToNext, 4),
                'milestones' => $milestones,
                'total_referrals' => $totalReferrals,
                'pending_rewards' => $pendingRewards,
                'milestone_first' => $first,
                'milestone_increment' => $increment,
            ],
        ]);
    }
}
