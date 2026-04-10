<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralPointTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralPointTransactionFactory> */
    use HasFactory;

    /**
     * Known reason codes. Use these constants when creating transactions
     * so typos don't leak into reports or milestone logic.
     */
    public const REASON_VENDOR_ONBOARDING = 'vendor_onboarding';

    public const REASON_REVERSAL = 'reversal';

    public const REASON_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'user_id',
        'referral_id',
        'points',
        'reason',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * The user who owns this points transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The referral this transaction was awarded for. Nullable for
     * adjustment rows that are not tied to a specific referral, and
     * stays populated (pointing at a possibly soft-deleted row) when
     * the associated referral is soft-deleted. Queries joining this
     * relationship should use withTrashed() when historical context
     * matters.
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
