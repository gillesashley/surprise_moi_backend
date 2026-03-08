<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, string> */
    private const STATUS_TITLES = [
        'confirmed' => 'Order Confirmed',
        'processing' => 'Order Processing',
        'fulfilled' => 'Order Fulfilled',
        'shipped' => 'Order Shipped',
        'delivered' => 'Order Delivered',
        'refunded' => 'Order Refunded',
    ];

    /** @var array<string, string> */
    private const STATUS_MESSAGES = [
        'confirmed' => 'Your order #%s has been confirmed',
        'processing' => 'Your order #%s is being processed',
        'fulfilled' => 'Your order #%s has been fulfilled',
        'shipped' => 'Your order #%s has been shipped',
        'delivered' => 'Your order #%s has been delivered',
        'refunded' => 'Your order #%s has been refunded',
    ];

    public function __construct(
        public Order $order,
        public string $newStatus
    ) {
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'title' => self::STATUS_TITLES[$this->newStatus] ?? 'Order Updated',
            'message' => sprintf(
                self::STATUS_MESSAGES[$this->newStatus] ?? 'Your order #%s has been updated',
                $this->order->order_number
            ),
            'action_url' => "/orders/{$this->order->id}",
            'actor' => null,
            'subject' => [
                'id' => $this->order->id,
                'type' => 'order',
                'order_number' => $this->order->order_number,
                'status' => $this->newStatus,
            ],
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
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
            ]);
    }
}
