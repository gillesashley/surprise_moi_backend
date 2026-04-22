<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VendorBalance;
use App\Models\VendorTransaction;
use Illuminate\Support\Facades\DB;

class VendorBalanceService
{
    /**
     * Get or create vendor balance.
     */
    public function getOrCreateBalance(int $vendorId): VendorBalance
    {
        return VendorBalance::firstOrCreate(
            ['vendor_id' => $vendorId],
            [
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'currency' => 'GHS',
            ]
        );
    }

    /**
     * Credit vendor's pending balance when order is paid.
     * Deducts platform commission based on vendor tier before crediting.
     */
    public function creditPendingBalance(Order $order): ?VendorTransaction
    {
        // If order has no vendor, skip commission calculation
        if (! $order->vendor_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $balance = $this->getOrCreateBalance($order->vendor_id);

            // Get vendor and calculate commission
            $vendor = $order->vendor;
            $commissionRate = $vendor ? $vendor->getCommissionRate() : 0;
            $commissionAmount = ($order->total * $commissionRate) / 100;
            $vendorPayoutAmount = $order->total - $commissionAmount;

            // Update order with commission details
            $order->update([
                'platform_commission_rate' => $commissionRate,
                'platform_commission_amount' => $commissionAmount,
                'vendor_payout_amount' => $vendorPayoutAmount,
            ]);

            // Credit vendor's pending balance with amount after commission
            $balance->increment('pending_balance', $vendorPayoutAmount);
            $balance->increment('total_earned', $vendorPayoutAmount);

            return VendorTransaction::create([
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'type' => VendorTransaction::TYPE_CREDIT_SALE,
                'amount' => $vendorPayoutAmount,
                'currency' => $order->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => "Payment received for order {$order->order_number} (after {$commissionRate}% platform commission)",
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_total' => $order->total,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'vendor_payout_amount' => $vendorPayoutAmount,
                ],
            ]);
        });
    }

    /**
     * Release funds from pending to available when order is delivered/fulfilled.
     */
    public function releaseFunds(Order $order): VendorTransaction
    {
        return DB::transaction(function () use ($order) {
            $balance = $this->getOrCreateBalance($order->vendor_id);

            // Use vendor_payout_amount (after commission) instead of total
            $amount = $order->vendor_payout_amount ?? $order->total;

            $balance->decrement('pending_balance', $amount);
            $balance->increment('available_balance', $amount);

            return VendorTransaction::create([
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'type' => VendorTransaction::TYPE_RELEASE_FUNDS,
                'amount' => $amount,
                'currency' => $order->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => "Funds released for delivered order {$order->order_number}",
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_total' => $order->total,
                    'vendor_payout_amount' => $amount,
                ],
            ]);
        });
    }

    /**
     * Process payout to vendor.
     */
    public function processPayout(int $vendorId, float $amount, ?string $description = null): VendorTransaction
    {
        return DB::transaction(function () use ($vendorId, $amount, $description) {
            $balance = $this->getOrCreateBalance($vendorId);

            if ($balance->available_balance < $amount) {
                throw new \Exception('Insufficient available balance for payout.');
            }

            $balance->decrement('available_balance', $amount);
            $balance->increment('total_withdrawn', $amount);

            return VendorTransaction::create([
                'vendor_id' => $vendorId,
                'type' => VendorTransaction::TYPE_PAYOUT,
                'amount' => $amount,
                'currency' => $balance->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => $description ?? 'Payout processed',
            ]);
        });
    }

    /**
     * Refund order - reverse the credit.
     */
    public function refundOrder(Order $order): VendorTransaction
    {
        return DB::transaction(function () use ($order) {
            $balance = $this->getOrCreateBalance($order->vendor_id);

            $existingTransaction = VendorTransaction::where('order_id', $order->id)
                ->where('type', VendorTransaction::TYPE_CREDIT_SALE)
                ->first();

            if (! $existingTransaction) {
                throw new \Exception('No credit transaction found for this order.');
            }

            // Use vendor_payout_amount (after commission) instead of total
            $amount = $order->vendor_payout_amount ?? $order->total;

            if ($existingTransaction->status === VendorTransaction::STATUS_COMPLETED) {
                $balance->decrement('pending_balance', $amount);
                $balance->decrement('total_earned', $amount);
            }

            return VendorTransaction::create([
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'type' => VendorTransaction::TYPE_REFUND,
                'amount' => $amount,
                'currency' => $order->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => "Refund for order {$order->order_number}",
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_total' => $order->total,
                    'vendor_payout_amount' => $amount,
                ],
            ]);
        });
    }
}
