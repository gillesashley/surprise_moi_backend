<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CompanyBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_code',
        'bank_name',
        'paystack_recipient_code',
        'is_active',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function treasuryTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class);
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Set this account as active, deactivating any other.
     */
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('is_active', true)->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
