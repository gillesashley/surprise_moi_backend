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

/**
 * VendorFlagReminderNotification
 *
 * Sent to a vendor by the scheduled flag-deadlines command roughly two days
 * before their grace period expires (configurable via the
 * `vendor_application_flag_reminder_days_before` setting). Distinct from
 * VendorFlaggedNotification so the bell-icon timeline reads cleanly:
 * flagged → reminder → resolution.
 *
 * Channels: database, mail, broadcast, FCM (when device token), SMS (when phone).
 *
 * Usage:
 *   $vendor->notify(new VendorFlagReminderNotification($vendorApplication));
 */
class VendorFlagReminderNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /**
     * `mixed` (not `object`) is required because HasSmsChannel declares the
     * abstract `via(mixed $notifiable)` — narrowing the type would be a PHP error.
     *
     * @return array<int, string>
     */
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
        return (new MailMessage)
            ->subject('Reminder: Your Vendor Application Is Due Soon')
            ->greeting("Hello {$notifiable->name},")
            ->line('This is a reminder that your vendor application still needs the details we requested.')
            ->line("Your deadline is **{$this->formattedDeadline()}**.")
            ->line('Please update and resubmit your application to keep it active.')
            ->action('Open the App', config('deep_links.share_base_url'))
            ->salutation('Best regards, The Surprise moi Team');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_flag_reminder',
            'title' => 'Reminder: Your Vendor Application Is Due Soon',
            'message' => "Your vendor application is due {$this->formattedDeadline()}. Please respond to avoid rejection.",
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
        // Use ASCII hyphen, not em-dash. The em-dash forces UCS-2 encoding,
        // dropping the segment cap from 160 to 70 chars and doubling cost.
        return (new SmsMessage)->content(
            "Surprise moi: Reminder - your vendor application is due {$this->formattedDeadline()}. Please respond to avoid rejection."
        );
    }

    private function formattedDeadline(): string
    {
        return $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';
    }
}
