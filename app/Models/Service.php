<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'shop_id',
        'name',
        'description',
        'service_type',
        'charge_start',
        'charge_end',
        'currency',
        'thumbnail',
        'availability',
        'rating',
        'reviews_count',
    ];

    protected function casts(): array
    {
        return [
            'charge_start' => 'decimal:2',
            'charge_end' => 'decimal:2',
            'rating' => 'decimal:2',
            'reviews_count' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
