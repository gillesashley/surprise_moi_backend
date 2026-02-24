<?php

namespace App\Models;

use App\Services\OrderNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    // Payment status constants
    public const PAYMENT_STATUS_UNPAID = 'unpaid';      // Order created, payment not initiated

    public const PAYMENT_STATUS_PENDING = 'pending';    // Payment initiated, awaiting confirmation

    public const PAYMENT_STATUS_PAID = 'paid';          // Payment successful

    public const PAYMENT_STATUS_FAILED = 'failed';      // Payment failed or declined

    public const PAYMENT_STATUS_REFUNDED = 'refunded';  // Payment reversed to customer

    /**
     * Mass-assignable attributes.
     * Includes financial calculations, status tracking, and delivery information.
     */
    protected $fillable = [
        'order_number',            // Auto-generated unique identifier (ORD-XXXXXXXXXX)
        'idempotency_key',         // Client-provided unique key for idempotent requests
        'user_id',                 // Customer who placed the order
        'vendor_id',               // Vendor fulfilling the order
        'rider_id',                // Assigned rider for delivery
        'subtotal',                // Sum of all items before discounts
        'discount_amount',         // Total discount applied
        'coupon_id',               // Applied coupon (if any)
        'delivery_fee',            // Shipping cost
        'total',                   // Final amount (subtotal - discount + delivery)
        'platform_commission_rate', // Platform commission rate percentage
        'platform_commission_amount', // Platform commission amount deducted
        'vendor_payout_amount',    // Amount payable to vendor after commission
        'currency',                // Default: GHS
        'status',                  // Order status: pending, confirmed, processing, fulfilled, shipped, delivered, refunded
        'payment_status',          // Payment status (see constants above)
        'delivery_address_id',     // Where to deliver the order
        'special_instructions',    // Customer notes for vendor
        'receiver_name',           // Name of the person receiving the gift
        'receiver_phone',          // Phone number of the receiver
        'delivery_method',         // vendor_self, platform_rider, third_party_courier
        'occasion',                // Gift occasion (birthday, anniversary, etc.)
        'scheduled_datetime',      // Scheduled delivery date/time
        'tracking_number',         // Delivery tracking number
        'delivery_pin',            // 4-digit PIN for delivery confirmation
        'delivery_confirmed_at',   // When delivery was confirmed with PIN
        'delivery_confirmed_by',   // Who confirmed delivery (delivery person name/ID)
        'confirmed_at',            // When vendor confirmed the order
        'fulfilled_at',            // When vendor marked as fulfilled
        'shipped_at',              // When order was shipped
        'delivered_at',            // When delivery was completed
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'platform_commission_rate' => 'decimal:2',
            'platform_commission_amount' => 'decimal:2',
            'vendor_payout_amount' => 'decimal:2',
            'scheduled_datetime' => 'datetime',
            'delivery_confirmed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_method' => 'string',
        ];
    }

    /**
     * Bootstrap the model.and delivery PIN on creation.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically generate unique order number when creating new order
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $service = app(OrderNumberService::class);
                $order->order_number = $service->generate($order);
            }

            // Generate unique 4-digit delivery PIN
            if (empty($order->delivery_pin)) {
                $order->delivery_pin = static::generateDeliveryPin();
            }
        });
    }

    /**
     * Generate a unique 4-digit delivery PIN.
     * Ensures PIN is unique across all orders.
     */
    public static function generateDeliveryPin(): string
    {
        $maxAttempts = 20;
        $attempts = 0;

        do {
            // Generate random 4-digit number
            $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $attempts++;

            if ($attempts >= $maxAttempts) {
                // Fallback: use timestamp-based PIN if we can't find unique one
                $pin = substr((string) time(), -4);
                break;
            }
        } while (static::where('delivery_pin', $pin)->where('delivery_confirmed_at', null)->exists());

        return $pin;
    }

    /**
     * Get the customer who placed this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vendor who will fulfill this order.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the rider assigned to deliver this order (if any).
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the coupon applied to this order (if any).
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the delivery address for this order.
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * Get all items in this order.
     * Each item represents a product or service with quantity.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all payments for this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the latest payment for this order.
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Get the successful payment for this order.
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', Payment::STATUS_SUCCESS);
    }

    /**
     * Check if the order is paid.
     *
     * @return bool True if payment_status is 'paid'
     */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Check if the order can be paid.
     * Order can be paid if it's unpaid/failed and has a non-zero total.
     *
     * @return bool True if order is eligible for payment
     */
    public function canBePaid(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_UNPAID, self::PAYMENT_STATUS_FAILED])
            && $this->total > 0;
    }

    /**
     * Scope: Get only pending orders.
     * Pending orders are awaiting vendor confirmation.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get only confirmed orders.
     * Confirmed orders have been accepted by the vendor.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope: Get only fulfilled orders.
     * Fulfilled orders are ready for delivery or have been handed to courier.
     */
    public function scopeFulfilled($query)
    {
        return $query->where('status', 'fulfilled');
    }

    /**
     * Scope: Get only shipped orders.
     * Shipped orders have been dispatched for delivery.
     */
    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    /**
     * Mark order as confirmed by vendor.
     * Updates status and records confirmation timestamp.
     */
    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Mark order as fulfilled.
     * Indicates order is ready for delivery.
     */
    public function markAsFulfilled(): void
    {
        $this->update([
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
        ]);
    }

    /**
     * Mark order as shipped.
     * Indicates order has been dispatched for delivery.
     */
    public function markAsShipped(): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    /**
     * Mark order as delivered.
     * Final status - order has reached customer.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
