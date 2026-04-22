<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Support\Facades\DB;

/**
 * ReferralService — manages the platform's referral program.
 *
 * Referral Lifecycle:
 * 1. Any user creates a referral code to share.
 * 2. A vendor uses the code during onboarding; a Referral row is created
 *    in 'pending' status and the vendor receives a subsidy on the
 *    onboarding fee.
 * 3. When the vendor application is approved, the referral activates and
 *    the referrer is awarded points based on a percentage of what the
 *    vendor actually paid. All roles earn points — no GHS Earning rows
 *    are created.
 */
class ReferralService
{
    /**
     * Create a referral code for any user.
     *
     * Any user can create referral codes to share. When a referral code
     * is used to onboard a vendor, earning-capable roles (influencer,
     * field_agent, employee) receive GHS rewards via the existing Earning
     * flow; all other roles receive points via ReferralPointTransaction.
     * The reward branching happens at referral activation time, not here.
     *
     * @param  User  $influencer  The sharer (column name is historical)
     * @param  string|null  $code  Custom code (auto-generated if null)
     * @param  string|null  $description  Campaign description
     * @param  float  $registrationBonus  One-time bonus when vendor approved (GHS)
     * @param  int|null  $maxUsages  Limit how many times code can be used
     * @param  \DateTime|null  $expiresAt  When code becomes invalid
     * @param  string|null  $prefix  Transient prefix for code generation
     */
    public function createReferralCode(
        User $influencer,
        ?string $code = null,
        ?string $description = null,
        float $registrationBonus = 0.00,
        ?int $maxUsages = null,
        ?\DateTime $expiresAt = null,
        ?string $prefix = null
    ): ReferralCode {
        $referralCode = new ReferralCode([
            'influencer_id' => $influencer->id,
            'code' => $code,
            'description' => $description,
            'registration_bonus' => $registrationBonus,
            'max_usages' => $maxUsages,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
        $referralCode->prefix = $prefix;
        $referralCode->save();

        return $referralCode;
    }

    /**
     * Bulk-generate referral codes for all users of a given role who
     * do not already have an active referral code.
     *
     * @return int Number of codes created
     */
    public function bulkGenerateCodes(string $role): int
    {
        $prefix = ReferralCode::getPrefixForRole($role);

        $query = User::query();
        if ($role === 'customer') {
            $query->where(function ($q) {
                $q->where('role', 'customer')->orWhereNull('role');
            });
        } else {
            $query->where('role', $role);
        }

        // Exclude users who already have an active referral code
        $query->whereDoesntHave('referralCodes', function ($q) {
            $q->where('is_active', true);
        });

        $users = $query->get();

        return DB::transaction(function () use ($users, $prefix) {
            $count = 0;
            foreach ($users as $user) {
                $this->createReferralCode(
                    influencer: $user,
                    description: 'Bulk-generated referral code',
                    prefix: $prefix,
                );
                $count++;
            }

            return $count;
        });
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

        // Defence in depth — also enforced at the validateReferralCode step.
        if ((int) $referralCode->influencer_id === (int) $vendorApplication->user_id) {
            throw new \RuntimeException('You cannot use your own referral code.');
        }

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
     * All roles earn referral points. Earning-capable roles (field_agent,
     * influencer, employee) also receive a GHS Earning record so their
     * dashboard earnings summary reflects the commission. The reward is a
     * percentage of what the vendor actually paid (post-subsidy), converted
     * to points via the `referral_points_per_ghs` setting.
     *
     * @return Referral|null Null if application has no referral code.
     */
    public function activateReferral(VendorApplication $vendorApplication): ?Referral
    {
        if (! $vendorApplication->referral_code_id) {
            return null;
        }

        $referral = Referral::where('vendor_application_id', $vendorApplication->id)
            ->where('status', Referral::STATUS_PENDING)
            ->first();

        if (! $referral) {
            return null;
        }

        return DB::transaction(function () use ($referral, $vendorApplication) {
            $referral->activate();

            // Pessimistic lock prevents races with concurrent role changes.
            $sharer = User::lockForUpdate()->findOrFail($referral->influencer_id);

            $percentage = (float) Setting::get("referral_bonus_{$sharer->role}_pct", 0);
            $finalAmount = (float) $vendorApplication->final_amount;
            $ghsAmount = round(($percentage / 100) * $finalAmount, 2);

            $pointsPerGhs = (int) Setting::get('referral_points_per_ghs', 10);
            $points = (int) round($ghsAmount * $pointsPerGhs);

            $referral->update(['earned_amount' => $ghsAmount]);

            if ($points > 0) {
                $this->awardPoints($referral, $points);
            }

            // Earning-capable roles also get a GHS Earning record so the
            // dashboard earnings summary is populated correctly.
            if ($sharer->isEarningCapable() && $ghsAmount > 0) {
                Earning::create([
                    'user_id' => $sharer->id,
                    'user_role' => $sharer->role,
                    'earning_type' => Earning::TYPE_REFERRAL_BONUS,
                    'earnable_id' => $referral->id,
                    'earnable_type' => Referral::class,
                    'amount' => $ghsAmount,
                    'currency' => 'GHS',
                    'status' => Earning::STATUS_PENDING,
                    'description' => "Referral bonus for vendor onboarding: {$vendorApplication->user?->name}",
                    'earned_at' => now(),
                ]);
            }

            return $referral->fresh();
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
            'referral_points' => (int) ($influencer->referral_points ?? 0),
            'unpaid_earnings' => $influencer->getTotalUnpaidEarnings(),
            'paid_earnings' => $influencer->getTotalPaidEarnings(),
        ];
    }

    /**
     * Award referral points to a user and log the transaction.
     */
    public function awardPoints(
        Referral $referral,
        int $points,
        string $reason = 'vendor_onboarding',
        ?string $description = null
    ): ReferralPointTransaction {
        return DB::transaction(function () use ($referral, $points, $reason, $description) {
            // Pessimistic lock prevents races when multiple referrals activate
            // concurrently for the same sharer.
            $user = User::lockForUpdate()->findOrFail($referral->influencer_id);

            $user->increment('referral_points', $points);

            return ReferralPointTransaction::create([
                'user_id' => $user->id,
                'referral_id' => $referral->id,
                'points' => $points,
                'reason' => $reason,
                'description' => $description
                    ?? "Points for vendor onboarding: {$referral->vendor->name}",
            ]);
        });
    }
}
