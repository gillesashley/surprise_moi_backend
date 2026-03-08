<?php

namespace App\Observers;

use App\Models\Message;
use App\Notifications\NewChatMessage;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        $conversation = $message->conversation;
        $recipient = $conversation->getOtherParticipant($message->sender);

        if (! $recipient) {
            return;
        }

        $recipient->notify(new NewChatMessage($message->sender, $message));
    }
}
