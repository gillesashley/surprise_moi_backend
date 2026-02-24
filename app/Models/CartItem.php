<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /** @use HasFactory<\Database\Factories\CartItemFactory> */
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'vendor_id',
        'sku',
        'name',
        'unit_price_cents',
        'quantity',
        'line_total_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'line_total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($cartItem) {
            $cartItem->line_total_cents = $cartItem->unit_price_cents * $cartItem->quantity;
        });

        static::updating(function ($cartItem) {
            $cartItem->line_total_cents = $cartItem->unit_price_cents * $cartItem->quantity;
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    public function getLineTotalAttribute(): float
    {
        return $this->line_total_cents / 100;
    }
}
