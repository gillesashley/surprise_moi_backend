<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    // Category constants
    public const CATEGORY_ORDER_ISSUE = 'order_issue';

    public const CATEGORY_PRODUCT_PROBLEM = 'product_problem';

    public const CATEGORY_VENDOR_DISPUTE = 'vendor_dispute';

    public const CATEGORY_PAYMENT_ISSUE = 'payment_issue';

    public const CATEGORY_OTHER = 'other';

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'report_number',
        'user_id',
        'category',
        'description',
        'status',
        'order_id',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Report $report): void {
            if (empty($report->report_number)) {
                $report->report_number = self::generateReportNumber();
            }
        });
    }

    /**
     * Generate a unique report number in the format REP-YYYYMMDD-XXXX.
     */
    protected static function generateReportNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "REP-{$date}-";

        $lastReport = self::where('report_number', 'like', "{$prefix}%")
            ->orderByDesc('report_number')
            ->lockForUpdate()
            ->first();

        $sequence = $lastReport
            ? ((int) substr($lastReport->report_number, -4)) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get available report categories with labels and icons.
     *
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function getCategories(): array
    {
        return [
            ['value' => self::CATEGORY_ORDER_ISSUE, 'label' => 'Order Issue', 'icon' => 'local_shipping'],
            ['value' => self::CATEGORY_PRODUCT_PROBLEM, 'label' => 'Product Problem', 'icon' => 'inventory_2'],
            ['value' => self::CATEGORY_VENDOR_DISPUTE, 'label' => 'Vendor Dispute', 'icon' => 'store'],
            ['value' => self::CATEGORY_PAYMENT_ISSUE, 'label' => 'Payment Issue', 'icon' => 'payment'],
            ['value' => self::CATEGORY_OTHER, 'label' => 'Other', 'icon' => 'help_outline'],
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query): mixed
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query): mixed
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeResolved($query): mixed
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeCancelled($query): mixed
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function markAsInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    public function markAsResolved(int $resolvedBy, string $resolutionNotes): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    public function markAsCancelled(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);
    }
}
