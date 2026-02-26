<?php

namespace App\Notifications\Concerns;

use App\Channels\SmsChannel;
use App\Notifications\Messages\SmsMessage;

/**
 * Trait for notifications that support SMS channel.
 *
 * Provides default implementation for the toSms method
 * and helper methods for SMS channel configuration.
 */
trait HasSmsChannel
{
    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    abstract public function via(mixed $notifiable): array;

    /**
     * Get the SMS representation of the notification.
     *
     * This method should be implemented by the notification class
     * to return an SmsMessage instance.
     *
     * @param  mixed  $notifiable
     * @return SmsMessage|array<string, mixed>
     */
    abstract public function toSms(mixed $notifiable): SmsMessage|array;

    /**
     * Determine if the notification should be sent via SMS.
     *
     * @param  mixed  $notifiable
     * @param  string  $channel
     * @return bool
     */
    public function shouldSend(mixed $notifiable, string $channel): bool
    {
        if ($channel !== SmsChannel::class) {
            return true;
        }

        // Check if notifiable has a phone number
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return ! empty($notifiable->routeNotificationForSms());
        }

        if (isset($notifiable->phone)) {
            return ! empty($notifiable->phone);
        }

        if (isset($notifiable->phone_number)) {
            return ! empty($notifiable->phone_number);
        }

        if (isset($notifiable->mobile)) {
            return ! empty($notifiable->mobile);
        }

        return false;
    }
}
