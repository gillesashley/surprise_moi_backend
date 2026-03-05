<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PayoutRequest extends Model
{
    /** @use HasFactory<\Database\Factories\PayoutRequestFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_QUARTERLY_SALARY = 'quarterly_salary';

    protected $fillable = [
        'user_id',
        'request_number',
        'user_role',
        'amount',
        'currency',
        'payout_method',
        'mobile_money_number',
        'mobile_money_provider',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'rejection_reason',
        'processed_by',
        'processed_at',
        'paid_at',
        'notes',
        'paystack_transfer_code',
        'paystack_transfer_reference',
        'paystack_transfer_id',
        'payout_detail_id',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PayoutRequest $request) {
            if (empty($request->request_number)) {
                $request->request_number = static::generateRequestNumber();
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        do {
            $number = 'PYT-'.strtoupper(Str::random(10));
        } while (static::where('request_number', $number)->exists());

        return $number;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payoutDetail(): BelongsTo
    {
        return $this->belongsTo(VendorPayoutDetail::class, 'payout_detail_id');
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
