<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    /** @use HasFactory<\Database\Factories\SupportTicketFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const CATEGORIES = [
        'order_issue', 'product_problem', 'vendor_dispute', 'payment_issue',
        'delivery_issue', 'account_help', 'general_inquiry', 'follow_up',
        'check_in', 'onboarding_assistance', 'feedback', 'other',
    ];

    protected $fillable = [
        'ticket_number', 'subject', 'description', 'category', 'priority',
        'status', 'user_id', 'contact_name', 'contact_phone', 'contact_email',
        'order_id', 'report_id', 'assigned_to', 'created_by',
        'closure_note', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (SupportTicket $ticket): void {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    protected static function generateTicketNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "CST-{$date}-";
        $last = self::where('ticket_number', 'like', "{$prefix}%")
            ->orderByDesc('ticket_number')
            ->lockForUpdate()
            ->first();
        $sequence = $last ? ((int) substr($last->ticket_number, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SupportInteraction::class, 'ticket_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
