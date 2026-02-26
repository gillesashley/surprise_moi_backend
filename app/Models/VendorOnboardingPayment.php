<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VendorOnboardingPayment extends Model
{
    /** @use HasFactory<\Database\Factories\VendorOnboardingPaymentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'vendor_application_id',
        'coupon_id',
        'reference',
        'paystack_reference',
        'authorization_url',
        'access_code',
        'amount',
        'amount_in_kobo',
        'discount_amount',
        'currency',
        'channel',
        'payment_method_type',
        'status',
        'card_last4',
        'card_type',
        'card_exp_month',
        'card_exp_year',
        'card_bank',
        'mobile_money_number',
        'mobile_money_provider',
        'metadata',
        'log',
        'gateway_response',
        'ip_address',
        'failure_reason',
        'paid_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'amount_in_kobo' => 'integer',
            'discount_amount' => 'float',
            'metadata' => 'array',
            'log' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Generate a unique payment reference.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'VOP-' . strtoupper(Str::random(16));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorApplication(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_ABANDONED, self::STATUS_CANCELLED]);
    }

    public function markAsSuccessful(array $data = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_SUCCESS,
            'paid_at' => now(),
            'verified_at' => now(),
        ], $data));
    }

    public function markAsFailed(?string $reason = null, array $data = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'verified_at' => now(),
        ], $data));
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_ABANDONED, self::STATUS_CANCELLED]);
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }
}
