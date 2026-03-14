<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_email',
        'avatar_url',
    ];

    /**
     * Get the user that owns this social account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
