<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TierUpgradeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING_DOCUMENT = 'pending_document';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'vendor_id',
        'status',
        'payment_reference',
        'payment_amount',
        'payment_currency',
        'payment_verified_at',
        'business_certificate_document',
        'admin_id',
        'admin_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_amount' => 'integer',
            'payment_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'TUP-'.strtoupper(Str::random(16));
        } while (static::where('payment_reference', $reference)->exists());

        return $reference;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    public function isPendingDocument(): bool
    {
        return $this->status === self::STATUS_PENDING_DOCUMENT;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, [self::STATUS_APPROVED]);
    }

    public function canSubmitDocument(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_DOCUMENT,
            self::STATUS_REJECTED,
        ]);
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function scopeNonApproved($query): void
    {
        $query->where('status', '!=', self::STATUS_APPROVED);
    }

    public function scopePendingReview($query): void
    {
        $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeStalePendingPayment($query, int $hours = 24): void
    {
        $query->where('status', self::STATUS_PENDING_PAYMENT)
            ->where('created_at', '<', now()->subHours($hours));
    }

    public function getPaymentAmountInGhsAttribute(): ?float
    {
        return $this->payment_amount ? round($this->payment_amount / 100, 2) : null;
    }
}
