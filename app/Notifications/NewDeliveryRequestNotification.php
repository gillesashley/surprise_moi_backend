<?php

namespace App\Notifications;

use App\Models\DeliveryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewDeliveryRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public DeliveryRequest $deliveryRequest)
    {
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'delivery_request_id' => $this->deliveryRequest->id,
            'pickup_address' => $this->deliveryRequest->pickup_address,
            'dropoff_address' => $this->deliveryRequest->dropoff_address,
            'delivery_fee' => $this->deliveryRequest->delivery_fee,
            'distance_km' => $this->deliveryRequest->distance_km,
            'expires_at' => $this->deliveryRequest->expires_at?->toISOString(),
        ];
    }
}
