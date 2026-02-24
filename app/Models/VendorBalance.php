<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorBalance extends Model
{
    /** @use HasFactory<\Database\Factories\VendorBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'pending_balance',
        'available_balance',
        'total_earned',
        'total_withdrawn',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id', 'vendor_id');
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) ($this->pending_balance + $this->available_balance);
    }
}
