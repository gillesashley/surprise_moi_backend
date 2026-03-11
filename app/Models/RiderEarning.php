<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderEarning extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rider_id',
        'order_id',
        'delivery_request_id',
        'amount',
        'type',
        'status',
        'available_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_at' => 'datetime',
        ];
    }

    /**
     * Get the rider who earned this.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the order associated with this earning.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the delivery request associated with this earning.
     */
    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    /**
     * Scope: only available earnings.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope: only pending earnings.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
