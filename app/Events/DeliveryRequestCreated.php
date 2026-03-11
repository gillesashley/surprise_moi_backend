<?php

namespace App\Events;

use App\Models\DeliveryRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DeliveryRequestCreated Event
 *
 * Fired when a new delivery request is created for an order.
 * Broadcasts to the order channel so the customer can be notified
 * that a rider is being dispatched.
 */
class DeliveryRequestCreated implements ShouldBroadcast
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
        ];
    }
}
