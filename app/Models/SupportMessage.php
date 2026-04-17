<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** @use HasFactory<\Database\Factories\SupportMessageFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const TEMPLATE_KEYS = ['birthday', 'welcome', 'follow_up', 'custom'];

    protected $fillable = [
        'ticket_id', 'interaction_id', 'to_user_id', 'to_phone', 'body',
        'template_key', 'status', 'failed_reason', 'sent_at', 'sent_by',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(SupportInteraction::class, 'interaction_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
