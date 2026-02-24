<?php

namespace App\Notifications\Sms;

use App\Channels\SmsChannel;
use App\Notifications\Concerns\HasSmsChannel;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * OTP Notification.
 *
 * Example notification that demonstrates the SMS channel usage.
 * Can be sent via: $user->notify(new OtpNotification($code));
 */
class OtpNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    /**
     * The OTP code.
     */
    protected string $code;

    /**
     * Custom message template (optional).
     */
    protected ?string $customMessage;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code  The OTP code to send
     * @param  string|null  $customMessage  Custom message template with {code} placeholder
     */
    public function __construct(string $code, ?string $customMessage = null)
    {
        $this->code = $code;
        $this->customMessage = $customMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [SmsChannel::class];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): SmsMessage
    {
        $message = $this->customMessage ?? 'Your Surprise Moi verification code is {code}. It expires in 10 minutes.';
        $message = str_replace('{code}', $this->code, $message);

        return (new SmsMessage)
            ->to($this->getPhoneNumber($notifiable))
            ->content($message);
    }

    /**
     * Get the phone number from the notifiable.
     */
    protected function getPhoneNumber(mixed $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms();
        }

        return $notifiable->phone ?? $notifiable->phone_number ?? $notifiable->mobile ?? null;
    }
}
