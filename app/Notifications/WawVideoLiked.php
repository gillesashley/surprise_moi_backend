<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WawVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class WawVideoLiked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $liker,
        public WawVideo $video
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
            'type' => 'waw_video_liked',
            'title' => 'Someone liked your video',
            'message' => "{$this->liker->name} liked your video",
            'action_url' => "/waw/videos/{$this->video->id}",
            'actor' => [
                'id' => $this->liker->id,
                'name' => $this->liker->name,
                'avatar' => $this->liker->avatar,
            ],
            'subject' => [
                'id' => $this->video->id,
                'type' => 'waw_video',
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
