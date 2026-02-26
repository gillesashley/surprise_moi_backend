<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Cart - Shopping cart for authenticated users and guests.
 * 
 * Storage Strategy:
 * - All monetary values stored as cents (integers) for precision
 * - Accessors convert to decimal for display (e.g., subtotal_cents -> subtotal)
 * - Version field incremented on each update for cache invalidation
 * 
 * Guest Carts:
 * - Identified by cart_token (UUID)
 * - Merged into user cart on login
 * 
 * Authenticated Carts:
 * - Identified by user_id
 * - One cart per user
 */
class Cart extends Model
{
    /** @use HasFactory<\Database\Factories\CartFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes.
     * 
     * All monetary values stored as cents (integer) to avoid floating-point precision issues.
     * Example: GHS 10.50 stored as 1050 cents
     */
    protected $fillable = [
        'user_id',          // Authenticated user (null for guest carts)
        'cart_token',       // UUID for guest cart identification
        'currency',         // Default: GHS
        'subtotal_cents',   // Sum of all cart items (in cents)
        'shipping_cents',   // Delivery fee (in cents)
        'tax_cents',        // Tax amount (in cents)
        'discount_cents',   // Coupon/discount amount (in cents)
        'total_cents',      // Final total (in cents)
        'metadata',         // Additional data (JSON)
        'version',          // Incremented on updates for caching
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'tax_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * Boot the model.
     * Auto-generates cart_token for guest carts.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Generate UUID token for guest carts (when user_id is null)
        static::creating(function ($cart) {
            if (empty($cart->cart_token) && empty($cart->user_id)) {
                $cart->cart_token = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Recalculate cart totals based on cart items.
     * Should be called after adding/removing/updating items.
     */
    public function recalculateTotals(): void
    {
        $this->subtotal_cents = $this->items->sum('line_total_cents');
        $this->total_cents = $this->subtotal_cents + $this->shipping_cents + $this->tax_cents - $this->discount_cents;
    }

    // Accessor methods: Convert cents to decimal for display

    /**
     * Get subtotal in decimal format (GHS).
     * Example: 1050 cents -> 10.50
     */
    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_cents / 100;
    }

    /**
     * Get shipping cost in decimal format (GHS).
     */
    public function getShippingAttribute(): float
    {
        return $this->shipping_cents / 100;
    }

    /**
     * Get tax in decimal format (GHS).
     */
    public function getTaxAttribute(): float
    {
        return $this->tax_cents / 100;
    }

    /**
     * Get discount in decimal format (GHS).
     */
    public function getDiscountAttribute(): float
    {
        return $this->discount_cents / 100;
    }

    /**
     * Get total in decimal format (GHS).
     */
    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    /**
     * Check if cart has no items.
     */
    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }
}
