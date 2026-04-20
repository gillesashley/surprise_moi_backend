<?php

namespace App\Models;

use App\Enums\VendorVisitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorVisit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_user_id',
        'field_agent_user_id',
        'status',
        'started_at',
        'submitted_at',
        'visit_latitude',
        'visit_longitude',
        'storefront_photo_path',
        'owner_photo_path',
        'notes',
        'escalated',
        'computed_result',
        'admin_override_result',
        'admin_override_reason',
        'admin_override_by',
        'admin_override_at',
        'badge_issued_at',
        'badge_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorVisitStatus::class,
            'computed_result' => VendorVisitStatus::class,
            'admin_override_result' => VendorVisitStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'admin_override_at' => 'datetime',
            'badge_issued_at' => 'datetime',
            'badge_expires_at' => 'datetime',
            'escalated' => 'boolean',
            'visit_latitude' => 'decimal:7',
            'visit_longitude' => 'decimal:7',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_agent_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorVisitItem::class);
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_override_by');
    }

    public function effectiveResult(): ?VendorVisitStatus
    {
        return $this->admin_override_result ?? $this->computed_result;
    }
}
