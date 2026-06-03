<?php

namespace App\Notifications;

use App\Models\DeliveryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

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
        return ['database', 'fcm'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_delivery_request',
            'title' => 'New Delivery Request',
            'message' => 'Pickup: ' . $this->deliveryRequest->pickup_address
                . ' — Fee: GHS ' . number_format($this->deliveryRequest->delivery_fee, 2),
            'action_url' => '/deliveries',
            'delivery_request_id' => $this->deliveryRequest->id,
            'pickup_address' => $this->deliveryRequest->pickup_address,
            'dropoff_address' => $this->deliveryRequest->dropoff_address,
            'delivery_fee' => $this->deliveryRequest->delivery_fee,
            'distance_km' => $this->deliveryRequest->distance_km,
            'expires_at' => $this->deliveryRequest->expires_at?->toISOString(),
        ];
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        $data = $this->toDatabase($notifiable);

        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($data['title'])
                    ->body($data['message'])
            )
            ->data([
                'type' => $data['type'],
                'action_url' => $data['action_url'],
                'delivery_request_id' => $data['delivery_request_id'],
            ]);
    }
}
