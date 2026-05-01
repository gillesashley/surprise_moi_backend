<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\VendorApplication;
use App\Notifications\Concerns\HasSmsChannel;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class VendorFlaggedNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        $channels = ['database', 'mail', BroadcastChannel::class];

        if ($notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new MailMessage)
            ->subject('Action Required: Update Your Vendor Application')
            ->greeting("Hello {$notifiable->name},")
            ->line('We need a few more details before we can finish reviewing your vendor application on Surprise moi.')
            ->line('**Reason:** '.$this->vendorApplication->flag_reason)
            ->line("Please update your application by {$deadline} to continue.")
            ->action('Open the App', config('deep_links.share_base_url'))
            ->line('If you have questions, please contact our support team at operations@teczaleel.com or WhatsApp us on +233 245 261 266.')
            ->salutation('Best regards, The Surprise moi Team');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return [
            'type' => 'vendor_flagged',
            'title' => 'Action Required on Your Vendor Application',
            'message' => "We need more details on your application. Please respond by {$deadline}.",
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => 'flagged',
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'title' => $data['title'],
            'body' => $data['message'],
            'status' => 'flagged',
            'vendor_application_id' => $this->vendorApplication->id,
            'flag_reason' => $this->vendorApplication->flag_reason,
            'grace_period_ends_at' => $this->vendorApplication->grace_period_ends_at?->toIso8601String(),
            'action_url' => $data['action_url'],
        ]);
    }

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

    public function toSms(mixed $notifiable): SmsMessage
    {
        $reason = mb_substr((string) $this->vendorApplication->flag_reason, 0, 80);
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new SmsMessage)->content(
            "Surprise moi: Your vendor application needs more details. Reason: {$reason}. Please update by {$deadline}."
        );
    }
}
