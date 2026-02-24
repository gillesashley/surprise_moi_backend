<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_purchase_amount',
        'max_discount_amount',
        'currency',
        'usage_limit',
        'used_count',
        'user_limit_per_user',
        'valid_from',
        'valid_until',
        'is_active',
        'vendor_id',
        'applicable_to',
        'specific_ids',
        'description',
        'title',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'user_limit_per_user' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'specific_ids' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(): bool
    {
        return $this->is_active
            && now()->between($this->valid_from, $this->valid_until)
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    public function canBeUsedBy(User $user): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $userUsageCount = $this->usages()->where('user_id', $user->id)->count();

        return $userUsageCount < $this->user_limit_per_user;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->min_purchase_amount && $subtotal < $this->min_purchase_amount) {
            return 0;
        }

        $discount = match ($this->type) {
            'percentage' => $subtotal * ($this->value / 100),
            'fixed' => $this->value,
            'cashback' => $this->value,
            default => 0,
        };

        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        return round($discount, 2);
    }
}
