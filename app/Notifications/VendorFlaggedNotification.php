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
use Illuminate\Support\Str;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * VendorFlaggedNotification
 *
 * Sent to a vendor when an admin flags their application for missing or unclear
 * details. Delivered across five channels:
 *
 * 1. Database — persisted for the in-app notification list
 * 2. Mail     — full reason, deadline, and CTA back to the app
 * 3. Broadcast — real-time Reverb push for connected web clients
 * 4. FCM      — push notification when the vendor has a registered device
 * 5. SMS      — short reminder, gated on the vendor having a phone number
 *
 * Usage:
 *   $vendor->notify(new VendorFlaggedNotification($vendorApplication));
 */
class VendorFlaggedNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    /**
     * SMS reason cap. The full template — prefix + reason + deadline — must fit
     * inside one 160-char GSM-7 segment to avoid double billing and concat issues.
     */
    private const SMS_REASON_MAX = 50;

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
            ->subject('Action Required: Update Your Vendor Application')
            ->greeting("Hello {$notifiable->name},")
            ->line('We need a few more details before we can finish reviewing your vendor application on Surprise moi.')
            ->line('**Reason:** '.$this->vendorApplication->flag_reason)
            ->line("Please update your application by {$this->formattedDeadline()} to continue.")
            ->action('Open the App', config('deep_links.share_base_url'))
            ->line('If you have questions, please contact our support team at operations@teczaleel.com or WhatsApp us on +233 245 261 266.')
            ->salutation('Best regards, The Surprise moi Team');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_flagged',
            'title' => 'Action Required on Your Vendor Application',
            'message' => "We need more details on your application. Please respond by {$this->formattedDeadline()}.",
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
        $reason = Str::limit((string) $this->vendorApplication->flag_reason, self::SMS_REASON_MAX, '');

        return (new SmsMessage)->content(
            "Surprise moi: Your vendor application needs more details. Reason: {$reason}. Please update by {$this->formattedDeadline()}."
        );
    }

    private function formattedDeadline(): string
    {
        return $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';
    }
}
