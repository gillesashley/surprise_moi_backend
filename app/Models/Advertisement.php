<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advertisement extends Model
{
    /** @use HasFactory<\Database\Factories\AdvertisementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'link_url',
        'status',
        'placement',
        'target_audience',
        'display_order',
        'start_date',
        'end_date',
        'clicks',
        'impressions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_audience' => 'array',
            'display_order' => 'integer',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeByPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    public function incrementImpressions(): void
    {
        $this->increment('impressions');
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }
}
