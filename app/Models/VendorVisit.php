<?php

namespace App\Models;

use App\Enums\VendorVisitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorVisit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_user_id',
        'field_agent_user_id',
        'vendor_application_id',
        'status',
        'started_at',
        'submitted_at',
        'storefront_photo_path',
        'owner_photo_path',
        'ghana_card_number',
        'tin_number',
        'has_shop',
        'shop_location',
        'primary_business_address',
        'computed_result',
        'admin_override_result',
        'admin_override_reason',
        'admin_override_by',
        'admin_override_at',
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
            'has_shop' => 'boolean',
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

    public function vendorApplication(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class, 'vendor_application_id');
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
