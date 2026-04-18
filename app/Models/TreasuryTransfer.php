<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TreasuryTransfer extends Model
{
    use Auditable, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_OTP_REQUIRED = 'otp_required';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    public function retentionClass(string $eventName): string
    {
        return 'critical';
    }

    protected $fillable = [
        'company_bank_account_id',
        'initiated_by',
        'amount',
        'amount_in_pesewas',
        'paystack_transfer_code',
        'paystack_reference',
        'status',
        'paystack_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paystack_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TreasuryTransfer $transfer) {
            if (empty($transfer->paystack_reference)) {
                $transfer->paystack_reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'TRS-'.strtoupper(Str::random(10));
        } while (static::where('paystack_reference', $reference)->exists());

        return $reference;
    }

    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
