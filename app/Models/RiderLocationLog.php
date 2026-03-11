<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderLocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rider_id',
        'delivery_request_id',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the rider for this location log.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the delivery request associated with this log entry.
     */
    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }
}
