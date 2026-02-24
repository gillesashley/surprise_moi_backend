<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'referral_code_id',
        'influencer_id',
        'vendor_id',
        'vendor_application_id',
        'status',
        'earned_amount',
        'activated_at',
        'commission_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_amount' => 'decimal:2',
            'activated_at' => 'datetime',
            'commission_expires_at' => 'datetime',
        ];
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function vendorApplication(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class);
    }

    public function earnings(): MorphMany
    {
        return $this->morphMany(Earning::class, 'earnable');
    }

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
            'commission_expires_at' => now()->addMonths(
                $this->referralCode->commission_duration_months
            ),
        ]);
    }

    public function isCommissionActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->commission_expires_at
            && $this->commission_expires_at->isFuture();
    }

    public function expire(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithActiveCommission($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('commission_expires_at', '>', now());
    }
}
