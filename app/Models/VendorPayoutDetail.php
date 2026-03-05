<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayoutDetail extends Model
{
    /** @use HasFactory<\Database\Factories\VendorPayoutDetailFactory> */
    use HasFactory;

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'vendor_id',
        'payout_method',
        'account_name',
        'account_number',
        'bank_code',
        'bank_name',
        'provider',
        'paystack_recipient_code',
        'is_verified',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
