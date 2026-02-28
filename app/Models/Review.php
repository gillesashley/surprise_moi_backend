<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_id',
        'item_type',
        'item_id',
        'reviewable_id',
        'reviewable_type',
        'rating',
        'comment',
        'images',
        'is_verified_purchase',
        'helpful_count',
        'context_key',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'images' => 'array',
            'is_verified_purchase' => 'boolean',
            'helpful_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function helpfuls(): HasMany
    {
        return $this->hasMany(ReviewHelpful::class);
    }

    public function reviewImages(): HasMany
    {
        return $this->hasMany(ReviewImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function helpfulUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_helpfuls')
            ->withTimestamps();
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function getItemTypeAttribute(): ?string
    {
        if (! empty($this->attributes['item_type'])) {
            return $this->attributes['item_type'];
        }

        return match ($this->reviewable_type) {
            'product', Product::class => 'product',
            'service', Service::class => 'service',
            default => null,
        };
    }

    public function getItemIdAttribute(): ?int
    {
        if (! empty($this->attributes['item_id'])) {
            return (int) $this->attributes['item_id'];
        }

        return $this->reviewable_id ? (int) $this->reviewable_id : null;
    }

    /**
     * Normalize reviewable_type to the configured morph class alias.
     */
    protected function reviewableType(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                if (class_exists($value) && is_subclass_of($value, Model::class)) {
                    return (new $value)->getMorphClass();
                }

                return $value;
            }
        );
    }
}
