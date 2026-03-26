<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class NewOrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
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
        $channels = ['database', 'broadcast'];

        if ($notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $itemSummary = $this->order->items->first()?->orderable?->name ?? 'an item';
        $itemCount = $this->order->items->count();

        if ($itemCount > 1) {
            $itemSummary .= ' + '.($itemCount - 1).' more';
        }

        return [
            'type' => 'new_order',
            'title' => 'New Order Received',
            'message' => sprintf(
                'Order #%s — %s ordered %s (GHS %s)',
                $this->order->order_number,
                $this->order->user?->name ?? 'A customer',
                $itemSummary,
                number_format((float) $this->order->total, 2)
            ),
            'action_url' => "/orders/{$this->order->id}",
            'actor' => $this->order->user ? [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
            ] : null,
            'subject' => [
                'id' => $this->order->id,
                'type' => 'order',
                'order_number' => $this->order->order_number,
                'total' => (float) $this->order->total,
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
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): SmsMessage
    {
        $itemSummary = $this->order->items->first()?->orderable?->name ?? 'an item';
        $itemCount = $this->order->items->count();

        if ($itemCount > 1) {
            $itemSummary .= ' + '.($itemCount - 1).' more';
        }

        $content = sprintf(
            'Surprise moi: New order #%s! %s ordered %s (GHS %s). Log in to your dashboard to confirm and process this order.',
            $this->order->order_number,
            $this->order->user?->name ?? 'A customer',
            $itemSummary,
            number_format((float) $this->order->total, 2)
        );

        return (new SmsMessage)->content($content);
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
