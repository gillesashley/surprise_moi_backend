<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldAgentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FieldAgentApplication $application) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your field agent application is approved')
            ->greeting('Hi '.$this->application->first_name.',')
            ->line('Congratulations — your field agent application has been approved.')
            ->line('You can now sign in using the email and password you provided at registration.')
            ->action('Sign in', url('/login'));
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->content('Surprise Moi: Your field agent application is approved. Sign in at '.url('/login'));
    }
}
