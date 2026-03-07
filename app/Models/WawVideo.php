<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WawVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'video_url',
        'thumbnail_url',
        'caption',
        'product_id',
        'service_id',
    ];

    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(WawVideoLike::class);
    }

    /**
     * Eager-loadable relationship scoped to current user for is_liked check.
     * Usage: ->with(['currentUserLike' => fn ($q) => $q->where('user_id', $userId)])
     */
    public function currentUserLike(): HasOne
    {
        return $this->hasOne(WawVideoLike::class);
    }
}
