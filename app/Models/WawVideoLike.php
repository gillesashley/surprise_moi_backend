<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WawVideoLike extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'waw_video_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WawVideoLike $like) {
            $like->created_at = $like->created_at ?? now();
        });
    }

    public function wawVideo(): BelongsTo
    {
        return $this->belongsTo(WawVideo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
