<?php

namespace App\Models;

use Database\Factories\ReviewHelpfulFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewHelpful extends Model
{
    /** @use HasFactory<ReviewHelpfulFactory> */
    use HasFactory;

    protected $fillable = [
        'review_id',
        'user_id',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
