<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug();
            }
        });
    }

    /**
     * Generate a unique 16-character slug.
     */
    public static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(16);
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Attributes that can be mass-assigned.
     * Includes pricing, inventory, delivery, and SEO-related fields.
     */
    protected $fillable = [
        'slug',
        'category_id',
        'vendor_id',
        'shop_id',
        'name',
        'description',
        'detailed_description',
        'price',                    // Base price
        'discount_price',           // Price after discount (optional)
        'discount_percentage',      // Percentage off original price
        'currency',                 // Default: GHS
        'thumbnail',                // Main product image
        'stock',                    // Available quantity
        'is_available',             // Whether product can be purchased
        'is_featured',              // Show on homepage/featured section
        'rating',                   // Average review rating (0-5)
        'reviews_count',            // Total number of reviews
        'sizes',                    // Available sizes (JSON array)
        'colors',                   // Available colors (JSON array)
        'free_delivery',            // Whether delivery is free
        'delivery_fee',             // Delivery cost if not free
        'estimated_delivery_days',  // Days for delivery
        'return_policy',            // Return policy description
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'discount_percentage' => 'integer',
            'stock' => 'integer',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'rating' => 'decimal:2',
            'reviews_count' => 'integer',
            'sizes' => 'array',
            'colors' => 'array',
            'free_delivery' => 'boolean',
            'delivery_fee' => 'decimal:2',
        ];
    }

    /**
     * Get the category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the vendor (user) who owns this product.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the shop this product belongs to.
     * A vendor can organize products across multiple shops.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get all images for this product.
     * Images are ordered by sort_order (primary image first).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get all variants of this product.
     * Variants represent different combinations of size, color, etc.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get all tags associated with this product.
     * Tags are used for search and filtering.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Get all reviews for this product.
     * Uses polymorphic relationship to share reviews table with services.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Get the embedding for this product.
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(ProductEmbedding::class);
    }

    /**
     * Get all special offers for this product.
     */
    public function specialOffers(): HasMany
    {
        return $this->hasMany(SpecialOffer::class);
    }

    /**
     * Get the currently active special offer for this product.
     */
    public function activeOffer(): HasOne
    {
        return $this->hasOne(SpecialOffer::class)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * Get the effective selling price considering active special offers.
     *
     * Priority: active special offer > vendor discount_price > base price.
     */
    public function getEffectivePriceAttribute(): float
    {
        $offer = $this->relationLoaded('activeOffer')
            ? $this->activeOffer
            : $this->activeOffer()->first();

        if ($offer) {
            return round((float) $this->price * (1 - $offer->discount_percentage / 100), 2);
        }

        return (float) ($this->discount_price ?? $this->price);
    }

    /**
     * Get the effective discount percentage considering active special offers.
     */
    public function getEffectiveDiscountPercentageAttribute(): ?int
    {
        $offer = $this->relationLoaded('activeOffer')
            ? $this->activeOffer
            : $this->activeOffer()->first();

        if ($offer) {
            return $offer->discount_percentage;
        }

        return $this->discount_percentage;
    }
}
