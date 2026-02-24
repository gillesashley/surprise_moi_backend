<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /**
     * Create a payout request for a user.
     */
    public function createPayoutRequest(
        User $user,
        float $amount,
        string $payoutMethod,
        ?string $mobileMoneyNumber = null,
        ?string $mobileMoneyProvider = null,
        ?string $bankName = null,
        ?string $accountNumber = null,
        ?string $accountName = null
    ): PayoutRequest {
        $availableAmount = app(EarningService::class)->getAvailableForPayout($user);

        if ($amount > $availableAmount) {
            throw new \InvalidArgumentException("Requested amount (GHS {$amount}) exceeds available earnings (GHS {$availableAmount}).");
        }

        if ($amount < 10) {
            throw new \InvalidArgumentException('Minimum payout amount is GHS 10.');
        }

        return PayoutRequest::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'amount' => $amount,
            'currency' => 'GHS',
            'payout_method' => $payoutMethod,
            'mobile_money_number' => $mobileMoneyNumber,
            'mobile_money_provider' => $mobileMoneyProvider,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a payout request.
     */
    public function approve(PayoutRequest $payoutRequest, User $admin): PayoutRequest
    {
        if (! $admin->isAdmin()) {
            throw new \InvalidArgumentException('Only admins can approve payout requests.');
        }

        $payoutRequest->approve($admin);

        return $payoutRequest->fresh();
    }

    /**
     * Reject a payout request.
     */
    public function reject(PayoutRequest $payoutRequest, User $admin, string $reason): PayoutRequest
    {
        if (! $admin->isAdmin()) {
            throw new \InvalidArgumentException('Only admins can reject payout requests.');
        }

        $payoutRequest->reject($admin, $reason);

        return $payoutRequest->fresh();
    }

    /**
     * Mark a payout request as paid and update related earnings.
     */
    public function markAsPaid(PayoutRequest $payoutRequest): PayoutRequest
    {
        return DB::transaction(function () use ($payoutRequest) {
            $earnings = Earning::where('user_id', $payoutRequest->user_id)
                ->where('status', Earning::STATUS_APPROVED)
                ->orderBy('earned_at', 'asc')
                ->get();

            $remainingAmount = $payoutRequest->amount;

            foreach ($earnings as $earning) {
                if ($remainingAmount <= 0) {
                    break;
                }

                if ($earning->amount <= $remainingAmount) {
                    $earning->markAsPaid();
                    $remainingAmount -= $earning->amount;
                } else {
                    break;
                }
            }

            $payoutRequest->markAsPaid();

            return $payoutRequest->fresh();
        });
    }

    /**
     * Get payout statistics for a user.
     */
    public function getUserPayoutStats(User $user): array
    {
        $requests = PayoutRequest::where('user_id', $user->id)->get();

        return [
            'total_requests' => $requests->count(),
            'pending_requests' => $requests->where('status', PayoutRequest::STATUS_PENDING)->count(),
            'approved_requests' => $requests->where('status', PayoutRequest::STATUS_APPROVED)->count(),
            'paid_requests' => $requests->where('status', PayoutRequest::STATUS_PAID)->count(),
            'rejected_requests' => $requests->where('status', PayoutRequest::STATUS_REJECTED)->count(),
            'total_paid_out' => $requests->where('status', PayoutRequest::STATUS_PAID)->sum('amount'),
            'available_for_payout' => app(EarningService::class)->getAvailableForPayout($user),
        ];
    }
}
