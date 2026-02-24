<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class EarningService
{
    /**
     * Approve an earning.
     */
    public function approve(Earning $earning, User $admin): Earning
    {
        if (! $admin->isAdmin()) {
            throw new AuthorizationException('Only admins can approve earnings.');
        }

        $earning->approve($admin);

        return $earning->fresh();
    }

    /**
     * Approve multiple earnings at once.
     */
    public function approveMultiple(array $earningIds, User $admin): int
    {
        $earnings = Earning::whereIn('id', $earningIds)
            ->where('status', Earning::STATUS_PENDING)
            ->get();

        foreach ($earnings as $earning) {
            $earning->approve($admin);
        }

        return $earnings->count();
    }

    /**
     * Mark an earning as paid.
     */
    public function markAsPaid(Earning $earning): Earning
    {
        $earning->markAsPaid();

        return $earning->fresh();
    }

    /**
     * Get earnings summary for a user.
     */
    public function getUserEarningsSummary(User $user): array
    {
        $earnings = Earning::where('user_id', $user->id)->get();

        return [
            'total_earnings' => $earnings->sum('amount'),
            'pending_earnings' => $earnings->where('status', Earning::STATUS_PENDING)->sum('amount'),
            'approved_earnings' => $earnings->where('status', Earning::STATUS_APPROVED)->sum('amount'),
            'paid_earnings' => $earnings->where('status', Earning::STATUS_PAID)->sum('amount'),
            'earnings_by_type' => $earnings->groupBy('earning_type')->map(fn ($group) => $group->sum('amount')),
        ];
    }

    /**
     * Get earnings for a specific date range.
     */
    public function getEarningsForPeriod(User $user, \DateTime $startDate, \DateTime $endDate): Collection
    {
        return Earning::where('user_id', $user->id)
            ->whereBetween('earned_at', [$startDate, $endDate])
            ->orderBy('earned_at', 'desc')
            ->get();
    }

    /**
     * Get total unpaid earnings available for payout.
     */
    public function getAvailableForPayout(User $user): float
    {
        return (float) Earning::where('user_id', $user->id)
            ->whereIn('status', [Earning::STATUS_APPROVED])
            ->sum('amount');
    }

    /**
     * Get quarterly earnings for marketers (for sign-on bonus calculation).
     */
    public function getQuarterlyEarnings(User $user, int $year, int $quarter): float
    {
        if (! $user->isMarketer()) {
            return 0;
        }

        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        $startDate = \Carbon\Carbon::createFromDate($year, $startMonth, 1)->startOfMonth();
        $endDate = \Carbon\Carbon::createFromDate($year, $endMonth, 1)->endOfMonth();

        return (float) Earning::where('user_id', $user->id)
            ->where('earning_type', Earning::TYPE_SIGN_ON_BONUS)
            ->whereBetween('earned_at', [$startDate, $endDate])
            ->sum('amount');
    }
}
