<?php

namespace App\Events;

use App\Models\DeliveryRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DeliveryStatusUpdated Event
 *
 * Fired when a delivery request's status changes (e.g. accepted, picked_up, in_transit, delivered).
 * Broadcasts to delivery-specific and order-specific channels so both riders and customers
 * can receive real-time status updates.
 */
class DeliveryStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public DeliveryRequest $deliveryRequest) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("delivery.{$this->deliveryRequest->id}"),
            new Channel("order.{$this->deliveryRequest->order_id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'delivery_request_id' => $this->deliveryRequest->id,
            'status' => $this->deliveryRequest->status,
            'rider_latitude' => $this->deliveryRequest->rider?->current_latitude,
            'rider_longitude' => $this->deliveryRequest->rider?->current_longitude,
            'updated_at' => now()->toISOString(),
        ];
    }
}
