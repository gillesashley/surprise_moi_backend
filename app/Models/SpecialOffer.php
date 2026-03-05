<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialOffer extends Model
{
    /** @use HasFactory<\Database\Factories\SpecialOfferFactory> */
    use HasFactory;

    public const TAG_TODAYS_OFFERS = "Today's Offers";

    public const TAG_LIMITED_TIME = 'Limited Time!';

    public const TAG_SPECIAL_OFFERS = 'Special Offers';

    public const TAG_FESTIVAL_OFFERS = 'Festival Offers';

    public const TAG_FLASH_SALE = 'Flash Sale';

    protected $fillable = [
        'product_id',
        'discount_percentage',
        'tag',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function validTags(): array
    {
        return [
            self::TAG_TODAYS_OFFERS,
            self::TAG_LIMITED_TIME,
            self::TAG_SPECIAL_OFFERS,
            self::TAG_FESTIVAL_OFFERS,
            self::TAG_FLASH_SALE,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to only active offers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only current offers (active + within date range).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->active()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * Scope to offers belonging to a specific vendor (via product.shop).
     */
    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->whereHas('product.shop', function (Builder $q) use ($vendorId) {
            $q->where('vendor_id', $vendorId);
        });
    }
}
