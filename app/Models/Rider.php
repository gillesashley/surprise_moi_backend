<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'vehicle_type',
        'license_plate',
        'id_card_number',
        'is_active',
        'last_active_at',
        'email_verified_at',
        'phone_verified_at',
        'ghana_card_front',
        'ghana_card_back',
        'drivers_license',
        'vehicle_photo',
        'vehicle_category',
        'status',
        'is_online',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'device_token',
        'average_rating',
        'total_deliveries',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_active_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'password' => 'hashed',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'average_rating' => 'decimal:2',
            'total_deliveries' => 'integer',
        ];
    }

    /**
     * Check if rider status is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if rider status is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if rider status is under review.
     */
    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    /**
     * Check if rider is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if rider is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get all orders assigned to this rider.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all delivery requests for this rider.
     */
    public function deliveryRequests(): HasMany
    {
        return $this->hasMany(DeliveryRequest::class);
    }

    /**
     * Get all earnings for this rider.
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    /**
     * Get all withdrawal requests for this rider.
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(RiderWithdrawalRequest::class);
    }

    /**
     * Get the vendors this rider is associated with.
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vendor_riders', 'rider_id', 'vendor_id')
            ->withPivot('nickname', 'is_default')
            ->withTimestamps();
    }

    /**
     * Get all location logs for this rider.
     */
    public function locationLogs(): HasMany
    {
        return $this->hasMany(RiderLocationLog::class);
    }

    /**
     * Get the rider's available balance from completed earnings.
     */
    public function getAvailableBalanceAttribute(): float
    {
        return (float) $this->earnings()
            ->where('status', 'available')
            ->sum('amount');
    }

    /**
     * Get the rider's pending balance from uncleared earnings.
     */
    public function getPendingBalanceAttribute(): float
    {
        return (float) $this->earnings()
            ->where('status', 'pending')
            ->sum('amount');
    }

    /**
     * Scope: only online riders.
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope: only approved riders.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: riders near a given coordinate using Haversine formula.
     *
     * @param  float  $latitude  Center latitude
     * @param  float  $longitude  Center longitude
     * @param  float  $radiusKm  Search radius in kilometers
     */
    public function scopeNearby(Builder $query, float $latitude, float $longitude, float $radiusKm = 5.0): Builder
    {
        $haversine = '(6371 * acos(
            cos(radians(?)) * cos(radians(current_latitude))
            * cos(radians(current_longitude) - radians(?))
            + sin(radians(?)) * sin(radians(current_latitude))
        ))';

        return $query
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->selectRaw("*, {$haversine} AS distance_km", [$latitude, $longitude, $latitude])
            ->havingRaw('distance_km <= ?', [$radiusKm])
            ->orderBy('distance_km');
    }
}
