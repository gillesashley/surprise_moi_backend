<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    /** @use HasFactory<\Database\Factories\TargetFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_VENDOR_SIGNUPS = 'vendor_signups';

    public const TYPE_REVENUE_GENERATED = 'revenue_generated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'assigned_by',
        'user_role',
        'target_type',
        'target_value',
        'current_value',
        'bonus_amount',
        'overachievement_rate',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'achieved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'overachievement_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'achieved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(TargetAchievement::class);
    }

    public function latestAchievement(): HasOne
    {
        return $this->hasOne(TargetAchievement::class)->latestOfMany();
    }

    public function updateProgress(float $newValue): void
    {
        $this->current_value = $newValue;

        if ($newValue >= $this->target_value && $this->status === self::STATUS_ACTIVE) {
            $this->markAsCompleted();
        }

        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'achieved_at' => now(),
        ]);
    }

    public function getCompletionPercentage(): int
    {
        if ($this->target_value == 0) {
            return 0;
        }

        return (int) min(100, ($this->current_value / $this->target_value) * 100);
    }

    public function calculateTotalBonus(): float
    {
        $bonus = 0;

        if ($this->current_value >= $this->target_value) {
            $bonus = (float) $this->bonus_amount;

            if ($this->current_value > $this->target_value && $this->overachievement_rate > 0) {
                $overachievement = $this->current_value - $this->target_value;
                $overachievementBonus = ($overachievement / $this->target_value) * $this->bonus_amount * ($this->overachievement_rate / 100);
                $bonus += $overachievementBonus;
            }
        }

        return round($bonus, 2);
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->end_date);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('user_role', $role);
    }
}
