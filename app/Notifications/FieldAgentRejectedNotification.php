<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FieldAgentRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Update on your field agent application')
            ->greeting('Hi '.$this->application->first_name.',')
            ->line('We were unable to approve your field agent application.')
            ->line('Reason: '.($this->application->rejection_reason ?? 'Not specified.'))
            ->line('You are welcome to reapply once the issue is addressed.');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $reason = Str::limit((string) $this->application->rejection_reason, 80, '');

        return (new SmsMessage)
            ->content('Surprise Moi: Your field agent application was not approved. '.$reason);
    }
}
