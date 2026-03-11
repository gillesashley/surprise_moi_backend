<?php

namespace App\Services;

use App\Models\DeliveryRequest;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\RiderWithdrawalRequest;

class RiderBalanceService
{
    /**
     * Credit earnings for a completed delivery.
     */
    public function creditEarning(Rider $rider, DeliveryRequest $deliveryRequest): RiderEarning
    {
        return RiderEarning::create([
            'rider_id' => $rider->id,
            'order_id' => $deliveryRequest->order_id,
            'delivery_request_id' => $deliveryRequest->id,
            'amount' => $deliveryRequest->delivery_fee,
            'type' => 'delivery_fee',
            'status' => 'pending',
            'available_at' => now()->addHours(24),
        ]);
    }

    /**
     * Release pending earnings that have passed the hold period.
     */
    public function releasePendingEarnings(): int
    {
        return RiderEarning::where('status', 'pending')
            ->where('available_at', '<=', now())
            ->update(['status' => 'available']);
    }

    /**
     * Get balance summary for a rider.
     *
     * @return array{available: float, pending: float, total_earned: float, total_withdrawn: float}
     */
    public function getBalanceSummary(Rider $rider): array
    {
        return [
            'available' => (float) $rider->earnings()->where('status', 'available')->sum('amount'),
            'pending' => (float) $rider->earnings()->where('status', 'pending')->sum('amount'),
            'total_earned' => (float) $rider->earnings()->sum('amount'),
            'total_withdrawn' => (float) $rider->withdrawalRequests()
                ->where('status', 'completed')->sum('amount'),
        ];
    }

    /**
     * Process a withdrawal request.
     *
     * @return RiderWithdrawalRequest|null Null if insufficient balance
     */
    public function processWithdrawal(Rider $rider, float $amount, string $provider, string $number): ?RiderWithdrawalRequest
    {
        $available = (float) $rider->earnings()->where('status', 'available')->sum('amount');

        if ($amount > $available) {
            return null;
        }

        return $rider->withdrawalRequests()->create([
            'amount' => $amount,
            'mobile_money_provider' => $provider,
            'mobile_money_number' => $number,
            'status' => 'pending',
        ]);
    }
}
