<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * WelcomeNotification
 *
 * Sent to the user immediately after successful registration.
 * Channels: email only.
 *
 * Usage:
 *   $user->notify(new WelcomeNotification());
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Surprise Moi!')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your Surprise Moi account has been created successfully. Let the surprises begin!')
            ->line('You can now explore our marketplace and discover amazing vendors, products, and services.')
            ->action('Open the App', config('deep_links.share_base_url'))
            ->salutation('Best regards, The Surprise Moi Team');
    }
}
