<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldAgentApplicationReceivedNotification extends Notification implements ShouldQueue
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
            ->subject('We received your field agent application')
            ->greeting('Hi '.$this->application->first_name.',')
            ->line('Thanks for applying to become a Surprise Moi field agent.')
            ->line('Our team will review your application and get back to you shortly.')
            ->line('You will receive another message (email and SMS) once the review is complete.');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->content('Surprise Moi: We received your field agent application. We will notify you once reviewed.');
    }
}
