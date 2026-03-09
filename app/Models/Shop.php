<?php

namespace App\Models;

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
        'business_name',
        'owner_name',
        'slug',
        'description',
        'logo',
        'is_active',
        'location',
        'phone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
