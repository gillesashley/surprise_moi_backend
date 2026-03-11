<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRider extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'rider_id',
        'nickname',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the vendor (user) in this association.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the rider in this association.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
