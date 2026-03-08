<?php

namespace App\Notifications;

use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReviewReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $replier,
        public ReviewReply $reply
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
        return ['database', 'broadcast'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'review_replied',
            'title' => 'New Reply to Your Review',
            'message' => "{$this->replier->name} replied to your review",
            'action_url' => "/reviews/{$this->reply->review_id}",
            'actor' => [
                'id' => $this->replier->id,
                'name' => $this->replier->name,
                'avatar' => $this->replier->avatar,
            ],
            'subject' => [
                'id' => $this->reply->id,
                'type' => 'review_reply',
                'review_id' => $this->reply->review_id,
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
}
