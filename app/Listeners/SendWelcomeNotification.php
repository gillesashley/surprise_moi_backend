<?php

namespace App\Listeners;

use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;

class SendWelcomeNotification
{
    /**
     * Send a welcome notification when a new user registers.
     */
    public function handle(Registered $event): void
    {
        $event->user->notify(new WelcomeNotification);
    }
}
