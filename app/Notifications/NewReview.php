<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class NewReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $reviewer,
        public Review $review
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
        $itemType = $this->review->item_type;

        return [
            'type' => 'new_review',
            'title' => 'New Review',
            'message' => "{$this->reviewer->name} left a {$this->review->rating}-star review on your {$itemType}",
            'action_url' => "/reviews/{$this->review->id}",
            'actor' => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'avatar' => $this->reviewer->avatar,
            ],
            'subject' => [
                'id' => $this->review->id,
                'type' => 'review',
                'rating' => $this->review->rating,
                'reviewable_type' => $itemType,
                'reviewable_id' => $this->review->reviewable_id,
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
