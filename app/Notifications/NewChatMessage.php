<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $sender,
        public Message $message
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
        $preview = Str::limit($this->message->body, 80);

        return [
            'type' => 'new_chat_message',
            'title' => 'New Message',
            'message' => "{$this->sender->name}: {$preview}",
            'action_url' => "/conversations/{$this->message->conversation_id}",
            'actor' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar,
            ],
            'subject' => [
                'id' => $this->message->id,
                'type' => 'message',
                'conversation_id' => $this->message->conversation_id,
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
