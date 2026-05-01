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

/**
 * VendorFlagExpiredNotification
 *
 * Sent to admin and super_admin users by the scheduled flag-deadlines command
 * the first time it runs after a flagged application's grace period has expired
 * without resubmission. Lets admins know to act manually — there is no auto-rejection.
 *
 * Channels: database, mail, broadcast, SMS (when phone). No FCM (admin staff
 * do not get push notifications by project convention).
 *
 * Usage:
 *   Notification::send(User::admins()->get(), new VendorFlagExpiredNotification($app));
 */
class VendorFlagExpiredNotification extends Notification implements ShouldQueue
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

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorApplication->user?->name ?? 'a vendor';

        return (new MailMessage)
            ->subject('Vendor Application Grace Period Expired')
            ->greeting("Hello {$notifiable->name},")
            ->line("The grace period for {$vendorName}'s vendor application has expired without a response.")
            ->line('**Original reason:** '.$this->vendorApplication->flag_reason)
            ->line("**Deadline that passed:** {$this->formattedDeadline()}")
            ->action('Review Application', url('/dashboard/vendor-applications/'.$this->vendorApplication->id))
            ->salutation('Surprise moi admin alerts');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_flag_expired',
            'title' => 'Vendor Application Grace Period Expired',
            'message' => "Application #{$this->vendorApplication->id} grace period expired. Please review.",
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
            'vendor_application_id' => $this->vendorApplication->id,
            'action_url' => $data['action_url'],
        ]);
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)->content(
            "Surprise moi: Vendor application #{$this->vendorApplication->id} grace period expired. Please review."
        );
    }

    private function formattedDeadline(): string
    {
        return $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'unknown';
    }
}
