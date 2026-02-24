<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralCodeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'influencer_id',
        'code',
        'description',
        'is_active',
        'usage_count',
        'max_usages',
        'registration_bonus',
        'commission_rate',
        'commission_duration_months',
        'discount_percentage',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'max_usages' => 'integer',
            'registration_bonus' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_duration_months' => 'integer',
            'discount_percentage' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ReferralCode $code) {
            if (empty($code->code)) {
                $code->code = static::generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique referral code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the influencer who owns the referral code.
     */
    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    /**
     * Get all referrals using this code.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * Check if the code is currently valid.
     */
    public function isValid(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_usages === null || $this->usage_count < $this->max_usages);
    }

    /**
     * Check if the code has reached maximum usages.
     */
    public function hasReachedMaxUsages(): bool
    {
        return $this->max_usages !== null && $this->usage_count >= $this->max_usages;
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Scope to get only active codes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only valid codes.
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_usages')
                    ->orWhereRaw('usage_count < max_usages');
            });
    }
}
