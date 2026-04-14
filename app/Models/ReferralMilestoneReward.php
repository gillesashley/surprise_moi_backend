<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralMilestoneReward extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralMilestoneRewardFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'threshold',
        'points_at_milestone',
        'status',
        'reward_type',
        'reward_description',
        'reward_value',
        'fulfilled_by',
        'fulfilled_at',
        'admin_notes',
        'payout_request_id',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'points_at_milestone' => 'integer',
            'reward_value' => 'decimal:2',
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * The user whose milestone this is.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who marked this reward as fulfilled.
     */
    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    /**
     * Scope: only pending rewards (still awaiting admin action).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function payoutRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }
}
