<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportInteraction extends Model
{
    /** @use HasFactory<\Database\Factories\SupportInteractionFactory> */
    use HasFactory;

    public const CHANNELS = [
        'phone_call', 'sms', 'whatsapp', 'email', 'in_app_chat', 'in_person', 'other',
    ];

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'ticket_id', 'channel', 'direction', 'summary',
        'occurred_at', 'follow_up_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'follow_up_at' => 'date',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
