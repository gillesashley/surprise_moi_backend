<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VendorTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\VendorTransactionFactory> */
    use HasFactory;

    public const TYPE_CREDIT_SALE = 'credit_sale';

    public const TYPE_RELEASE_FUNDS = 'release_funds';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vendor_id',
        'order_id',
        'transaction_number',
        'type',
        'amount',
        'currency',
        'status',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VendorTransaction $transaction) {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = static::generateTransactionNumber();
            }
        });
    }

    public static function generateTransactionNumber(): string
    {
        do {
            $number = 'VTX-' . strtoupper(Str::random(10));
        } while (static::where('transaction_number', $number)->exists());

        return $number;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
