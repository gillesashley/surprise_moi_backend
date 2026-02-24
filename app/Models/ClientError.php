<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientError extends Model
{
    protected $table = 'client_errors';

    protected $fillable = [
        'user_id',
        'device_info',
        'occurred_at',
        'error',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'device_info' => 'array',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
