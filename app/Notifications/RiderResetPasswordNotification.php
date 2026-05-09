<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $deepLink = "surprisemoi-rider://reset-password?token={$this->token}&email=".urlencode($email);
        $webFallback = config('app.url').'/rider/reset-password?token='.$this->token.'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Reset your Surprise Moi rider password')
            ->greeting("Hello {$notifiable->name},")
            ->line('We received a request to reset your Surprise Moi rider password.')
            ->action('Reset Password', $deepLink)
            ->line('This link will expire in 60 minutes.')
            ->line("If the button does not open the app, copy this link: {$webFallback}")
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('— The Surprise Moi Team');
    }
}
