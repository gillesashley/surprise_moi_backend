<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_id',
        'rider_id',
        'vendor_id',
        'assigned_rider_id',
        'status',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'delivery_fee',
        'distance_km',
        'broadcast_radius_km',
        'broadcast_attempts',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'expires_at',
        'cancellation_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'delivery_fee' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'broadcast_radius_km' => 'decimal:2',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the order associated with this delivery request.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the rider who was initially assigned or broadcast to.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the vendor who created this delivery request.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the rider who accepted and is handling this delivery.
     */
    public function assignedRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'assigned_rider_id');
    }

    /**
     * Get the earning record for this delivery.
     */
    public function earning(): HasOne
    {
        return $this->hasOne(RiderEarning::class);
    }

    /**
     * Get all location logs for this delivery request.
     */
    public function locationLogs(): HasMany
    {
        return $this->hasMany(RiderLocationLog::class);
    }

    /**
     * Check if this delivery request is currently broadcasting.
     */
    public function isBroadcasting(): bool
    {
        return $this->status === 'broadcasting';
    }

    /**
     * Check if this delivery request has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if this delivery request is actively in progress.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['accepted', 'picked_up', 'in_transit']);
    }

    /**
     * Check if this delivery has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Scope: only active delivery requests (accepted, picked_up, in_transit).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['accepted', 'picked_up', 'in_transit']);
    }

    /**
     * Scope: only broadcasting delivery requests.
     */
    public function scopeBroadcasting(Builder $query): Builder
    {
        return $query->where('status', 'broadcasting');
    }
}
