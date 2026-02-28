<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewImage extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewImageFactory> */
    use HasFactory;

    protected $fillable = [
        'review_id',
        'storage_path',
        'sort_order',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
