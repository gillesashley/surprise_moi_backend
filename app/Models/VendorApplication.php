<?php

namespace App\Models;

use App\Events\VendorApprovalSubmitted;
use App\Events\VendorApproved;
use App\Events\VendorRejected;
use App\Notifications\VendorApplicationSubmittedNotification;
use App\Notifications\VendorApprovalNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorApplication extends Model
{
    /** @use HasFactory<\Database\Factories\VendorApplicationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Application status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Mobile money provider constants.
     */
    public const PROVIDER_MTN = 'mtn';

    public const PROVIDER_VODAFONE = 'vodafone';

    public const PROVIDER_AIRTELTIGO = 'airteltigo';

    protected $fillable = [
        'user_id',
        'status',
        'current_step',
        'completed_step',
        // Step 1: Ghana Card
        'ghana_card_front',
        'ghana_card_back',
        // Step 2: Business Registration Flags
        'has_business_certificate',
        // Step 3A: Registered Vendor Documents
        'business_certificate_document',
        // Step 3B: Unregistered Vendor Verification
        'selfie_image',
        'mobile_money_number',
        'mobile_money_provider',
        'proof_of_business',
        // Step 3 (Both): Social Media
        'facebook_handle',
        'instagram_handle',
        'twitter_handle',
        // Admin review
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
        // Payment fields
        'payment_required',
        'payment_completed',
        'payment_completed_at',
        'coupon_id',
        'onboarding_fee',
        'discount_amount',
        'final_amount',
    ];

    protected function casts(): array
    {
        return [
            'has_business_certificate' => 'boolean',
            'current_step' => 'integer',
            'completed_step' => 'integer',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'payment_required' => 'boolean',
            'payment_completed' => 'boolean',
            'payment_completed_at' => 'datetime',
            'onboarding_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function bespokeServices(): BelongsToMany
    {
        return $this->belongsToMany(BespokeService::class, 'vendor_application_services')
            ->withTimestamps();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function onboardingPayments(): HasMany
    {
        return $this->hasMany(VendorOnboardingPayment::class);
    }

    public function latestOnboardingPayment(): HasOne
    {
        return $this->hasOne(VendorOnboardingPayment::class)->latestOfMany();
    }

    /**
     * Check if vendor is registered (has business documents).
     */
    public function isRegisteredVendor(): bool
    {
        return (bool) $this->has_business_certificate;
    }

    /**
     * Get vendor tier (tier 1 = registered, tier 2 = unregistered).
     */
    public function getVendorTier(): int
    {
        return $this->isRegisteredVendor() ? 1 : 2;
    }

    /**
     * Get onboarding fee based on vendor tier.
     */
    public function getOnboardingFee(): float
    {
        $tier = $this->getVendorTier();
        $key = $tier === 1 ? 'vendor_tier1_onboarding_fee' : 'vendor_tier2_onboarding_fee';

        return (float) Setting::get($key, $tier === 1 ? 100 : 50);
    }

    /**
     * Calculate final amount after applying coupon.
     */
    public function calculateFinalAmount(?Coupon $coupon = null): array
    {
        $onboardingFee = $this->getOnboardingFee();
        $discountAmount = 0;

        if ($coupon && $coupon->isValid()) {
            $discountAmount = $coupon->calculateDiscount($onboardingFee);
        }

        $finalAmount = max(0, $onboardingFee - $discountAmount);

        return [
            'onboarding_fee' => $onboardingFee,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
        ];
    }

    /**
     * Check if step 3 documents are actually present for the current registration type.
     */
    public function isStep3Complete(): bool
    {
        if ($this->isRegisteredVendor()) {
            return ! empty($this->business_certificate_document);
        }

        return ! empty($this->selfie_image)
            && ! empty($this->mobile_money_number)
            && ! empty($this->mobile_money_provider)
            && ! empty($this->proof_of_business);
    }

    /**
     * Check if application can be submitted.
     */
    public function canSubmit(): bool
    {
        return $this->completed_step >= 4
            && $this->isStep3Complete()
            && $this->status === self::STATUS_PENDING
            && is_null($this->submitted_at)
            && (! $this->payment_required || $this->payment_completed);
    }

    /**
     * Check if application can be reviewed (approved/rejected) by an admin.
     */
    public function canBeReviewed(): bool
    {
        return $this->completed_step >= 4
            && (! $this->payment_required || $this->payment_completed)
            && ! is_null($this->submitted_at);
    }

    /**
     * Check if payment is required and not yet completed.
     */
    public function needsPayment(): bool
    {
        return $this->payment_required && ! $this->payment_completed;
    }

    /**
     * Check if application is editable.
     */
    public function isEditable(): bool
    {
        // Rejected applications can always be edited
        if ($this->status === self::STATUS_REJECTED) {
            return true;
        }

        // Pending applications can be edited only if not yet submitted
        return $this->status === self::STATUS_PENDING && is_null($this->submitted_at);
    }

    /**
     * Get available statuses.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Get available mobile money providers.
     *
     * @return array<string>
     */
    public static function getMobileMoneyProviders(): array
    {
        return [
            self::PROVIDER_MTN,
            self::PROVIDER_VODAFONE,
            self::PROVIDER_AIRTELTIGO,
        ];
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending applications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get applications for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Approve the vendor application.
     */
    public function approve(int $reviewerId): bool
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // Update user role to vendor and set vendor tier
        $this->user()->update([
            'role' => 'vendor',
            'vendor_tier' => $this->getVendorTier(),
        ]);

        // Fire approval event and send notification
        event(new VendorApproved($this));
        $this->user->notify(new VendorApprovalNotification($this, 'approved'));

        return true;
    }

    /**
     * Reject the vendor application.
     */
    public function reject(int $reviewerId, string $reason): bool
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Fire rejection event and send notification
        event(new VendorRejected($this));
        $this->user->notify(new VendorApprovalNotification($this, 'rejected'));

        return true;
    }

    /**
     * Mark application as under review.
     */
    public function markUnderReview(): bool
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_UNDER_REVIEW]);

            return true;
        }

        return false;
    }

    /**
     * Submit the vendor application for review.
     *
     * Fires VendorApprovalSubmitted event to notify admins in real-time.
     */
    public function submit(): bool
    {
        if (! $this->canSubmit()) {
            return false;
        }

        $this->update(['submitted_at' => now()]);

        // Fire submission event to notify admins
        event(new VendorApprovalSubmitted($this));

        // Send confirmation notification to the vendor
        $this->user->notify(new VendorApplicationSubmittedNotification($this));

        return true;
    }
}
