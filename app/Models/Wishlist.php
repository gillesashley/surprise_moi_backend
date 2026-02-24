<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'item_id',
        'item_type',
    ];

    /**
     * Get the user that owns the wishlist item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wishlistable item (Product or Service).
     */
    public function item(): MorphTo
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    /**
     * Scope: Filter by item type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope: Filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
