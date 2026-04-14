<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPayoutDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'payout_method',
        'mobile_money_number',
        'mobile_money_provider',
        'account_name',
        'paystack_recipient_code',
        'is_verified',
        'is_default',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function providerToPaystackBankCode(string $provider): string
    {
        return match ($provider) {
            'mtn' => 'MTN',
            'vodafone' => 'VOD',
            'airteltigo' => 'ATL',
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
}
