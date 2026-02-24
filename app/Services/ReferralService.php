<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Support\Facades\DB;

/**
 * ReferralService - Manages influencer referral system and commission tracking.
 *
 * Referral Lifecycle:
 * 1. Influencer creates referral code
 * 2. Vendor uses code during registration
 * 3. Referral created in 'pending' status
 * 4. When vendor application approved -> referral 'active' + registration bonus earned
 * 5. For X months, influencer earns commission % on vendor's orders
 * 6. After X months, commission expires
 *
 * Earnings Types:
 * - Registration Bonus: One-time payment when vendor approved
 * - Commission: Percentage of vendor's order total (during commission period)
 */
class ReferralService
{
    /**
     * Create a referral code for an influencer.
     *
     * Influencers can create multiple codes for different campaigns.
     * Each code can have its own bonus amount, commission rate, and duration.
     *
     * @param  User  $influencer  User with 'influencer' role
     * @param  string|null  $code  Custom code (auto-generated if null)
     * @param  string|null  $description  Campaign description
     * @param  float  $registrationBonus  One-time bonus when vendor approved (GHS)
     * @param  float  $commissionRate  Percentage of vendor's order total (0-100)
     * @param  int  $commissionDurationMonths  How long to earn commission
     * @param  int|null  $maxUsages  Limit how many times code can be used
     * @param  \DateTime|null  $expiresAt  When code becomes invalid
     *
     * @throws \InvalidArgumentException If user is not an influencer
     */
    public function createReferralCode(
        User $influencer,
        ?string $code = null,
        ?string $description = null,
        float $registrationBonus = 50.00,
        float $commissionRate = 5.00,
        int $commissionDurationMonths = 3,
        ?int $maxUsages = null,
        ?\DateTime $expiresAt = null
    ): ReferralCode {
        if (! $influencer->isInfluencer()) {
            throw new \InvalidArgumentException('User must be an influencer to create referral codes.');
        }

        return ReferralCode::create([
            'influencer_id' => $influencer->id,
            'code' => $code,
            'description' => $description,
            'registration_bonus' => $registrationBonus,
            'commission_rate' => $commissionRate,
            'commission_duration_months' => $commissionDurationMonths,
            'max_usages' => $maxUsages,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * Apply a referral code to a vendor application.
     *
     * Called during vendor registration when they provide a referral code.
     * Creates a referral record in 'pending' status until application approved.
     *
     * @param  VendorApplication  $vendorApplication  The application to attach code to
     * @param  string  $code  The referral code provided by vendor
     *
     * @throws \RuntimeException If application already has a code
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If code invalid
     */
    public function applyReferralCode(
        VendorApplication $vendorApplication,
        string $code
    ): Referral {
        // Validate code exists and is valid (not expired, not maxed out)
        $referralCode = ReferralCode::where('code', $code)->valid()->firstOrFail();

        // Prevent applying multiple codes to same application
        if ($vendorApplication->referral_code_id) {
            throw new \RuntimeException('This vendor application already has a referral code applied.');
        }

        return DB::transaction(function () use ($vendorApplication, $referralCode) {
            // Store code reference on vendor application
            $vendorApplication->update([
                'referral_code_id' => $referralCode->id,
                'referral_code_used' => $referralCode->code,
            ]);

            // Create referral record (pending until application approved)
            $referral = Referral::create([
                'referral_code_id' => $referralCode->id,
                'influencer_id' => $referralCode->influencer_id,
                'vendor_id' => $vendorApplication->user_id,
                'vendor_application_id' => $vendorApplication->id,
                'status' => Referral::STATUS_PENDING,
            ]);

            // Increment usage count for tracking
            $referralCode->incrementUsage();

            return $referral;
        });
    }

    /**
     * Activate a referral when vendor application is approved.
     *
     * This method:
     * 1. Changes referral status from 'pending' to 'active'
     * 2. Sets commission_starts_at and commission_expires_at dates
     * 3. Creates registration bonus earning for influencer (if configured)
     *
     * Called by admin when approving vendor application.
     *
     * @param  VendorApplication  $vendorApplication  The approved application
     * @return Referral|null Null if application has no referral code
     */
    public function activateReferral(VendorApplication $vendorApplication): ?Referral
    {
        // Check if application has a referral code
        if (! $vendorApplication->referral_code_id) {
            return null;
        }

        // Find pending referral for this application
        $referral = Referral::where('vendor_application_id', $vendorApplication->id)
            ->where('status', Referral::STATUS_PENDING)
            ->first();

        if (! $referral) {
            return null;
        }

        return DB::transaction(function () use ($referral) {
            // Activate referral and set commission period
            $referral->activate();

            // Create registration bonus earning if configured
            if ($referral->referralCode->registration_bonus > 0) {
                Earning::create([
                    'user_id' => $referral->influencer_id,
                    'user_role' => 'influencer',
                    'earning_type' => Earning::TYPE_REFERRAL_BONUS,
                    'earnable_id' => $referral->id,
                    'earnable_type' => Referral::class,
                    'amount' => $referral->referralCode->registration_bonus,
                    'currency' => 'GHS',
                    'status' => Earning::STATUS_PENDING,
                    'description' => "Registration bonus for referring vendor: {$referral->vendor->name}",
                    'earned_at' => now(),
                ]);
            }

            return $referral->fresh();
        });
    }

    /**
     * Calculate and create commission earnings from vendor orders.
     *
     * Called automatically by PaystackService after successful payment verification.
     * Only creates commission if referral is still within commission period.
     *
     * Example: If commission rate is 5% and order is GHS 1000, influencer earns GHS 50.
     *
     * @param  Referral  $referral  The active referral
     * @param  float  $orderAmount  Total order amount in GHS
     * @return Earning|null Null if commission period expired or amount is zero
     */
    public function calculateCommission(
        Referral $referral,
        float $orderAmount
    ): ?Earning {
        // Check if still within commission period
        if (! $referral->isCommissionActive()) {
            return null;
        }

        // Calculate commission amount
        $commissionAmount = ($orderAmount * $referral->referralCode->commission_rate) / 100;

        if ($commissionAmount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($referral, $commissionAmount, $orderAmount) {
            // Create earning record
            $earning = Earning::create([
                'user_id' => $referral->influencer_id,
                'user_role' => 'influencer',
                'earning_type' => Earning::TYPE_COMMISSION,
                'earnable_id' => $referral->id,
                'earnable_type' => Referral::class,
                'amount' => $commissionAmount,
                'currency' => 'GHS',
                'status' => Earning::STATUS_PENDING,
                'description' => "Commission from order (GHS {$orderAmount}) by vendor: {$referral->vendor->name}",
                'earned_at' => now(),
            ]);

            // Track total earned from this referral
            $referral->increment('earned_amount', $commissionAmount);

            return $earning;
        });
    }

    /**
     * Get referral statistics for an influencer.
     * Used in influencer dashboard to display performance metrics.
     *
     * @param  User  $influencer  The influencer to get stats for
     * @return array Statistics with counts and totals
     */
    public function getInfluencerStats(User $influencer): array
    {
        $referrals = Referral::where('influencer_id', $influencer->id)->get();

        return [
            'total_referrals' => $referrals->count(),
            'active_referrals' => $referrals->where('status', Referral::STATUS_ACTIVE)->count(),
            'pending_referrals' => $referrals->where('status', Referral::STATUS_PENDING)->count(),
            'total_earned' => $referrals->sum('earned_amount'),
            'unpaid_earnings' => $influencer->getTotalUnpaidEarnings(),
            'paid_earnings' => $influencer->getTotalPaidEarnings(),
        ];
    }

    /**
     * Expire referrals with expired commissions.
     *
     * Should be run daily via scheduled command.
     * Changes status from 'active' to 'expired' for referrals past commission period.
     *
     * @return int Number of referrals expired
     */
    public function expireCommissions(): int
    {
        $expired = Referral::where('status', Referral::STATUS_ACTIVE)
            ->where('commission_expires_at', '<', now())
            ->get();

        foreach ($expired as $referral) {
            $referral->expire();
        }

        return $expired->count();
    }
}
