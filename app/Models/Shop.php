<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shop extends Model
{
    /** @use HasFactory<\Database\Factories\ShopFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'owner_name',
        'slug',
        'description',
        'logo',
        'is_active',
        'location',
        'phone',
        'email',
        'service_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'service_hours' => 'array',
        ];
    }

    /**
     * Get service hours with default Mon-Fri 09:00-17:00 when not set.
     *
     * @return array<string, array{is_open: bool, open: string|null, close: string|null}>
     */
    protected function serviceHours(): Attribute
    {
        $default = [
            'monday'    => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
            'tuesday'   => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
            'wednesday' => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
            'thursday'  => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
            'friday'    => ['is_open' => true,  'open' => '09:00', 'close' => '17:00'],
            'saturday'  => ['is_open' => false, 'open' => null,    'close' => null],
            'sunday'    => ['is_open' => false, 'open' => null,    'close' => null],
        ];

        return Attribute::make(
            get: fn ($value) => $value ?? $default,
        );
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Shop $shop) {
            if (empty($shop->slug)) {
                $shop->slug = Str::slug($shop->name);
            }
        });

        static::updating(function (Shop $shop) {
            if ($shop->isDirty('name') && empty($shop->slug)) {
                $shop->slug = Str::slug($shop->name);
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Scope to get only active shops.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter shops by vendor.
     */
    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
