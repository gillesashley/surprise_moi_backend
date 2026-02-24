<?php

namespace App\Channels;

use App\Contracts\Sms\SmsProviderInterface;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Notifications\Notification;

/**
 * SMS Notification Channel.
 *
 * This channel routes SMS notifications through the configured
 * SMS provider implementation.
 */
class SmsChannel
{
    /**
     * The SMS provider instance.
     */
    protected SmsProviderInterface $smsProvider;

    /**
     * Create a new SMS channel instance.
     */
    public function __construct(SmsProviderInterface $smsProvider)
    {
        $this->smsProvider = $smsProvider;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable  The entity receiving the notification
     * @param  Notification  $notification  The notification instance
     * @return array{success: bool, message: string, data: array|null}
     */
    public function send(mixed $notifiable, Notification $notification): array
    {
        // Get the SMS message from the notification
        $message = $notification->toSms($notifiable);

        // If the notification returns an array, convert it to SmsMessage
        if (is_array($message)) {
            $message = $this->arrayToSmsMessage($message);
        }

        // Ensure we have an SmsMessage instance
        if (! $message instanceof SmsMessage) {
            throw new \InvalidArgumentException(
                'Notification must return an SmsMessage instance or array'
            );
        }

        // Get the recipient phone number
        $to = $message->getTo() ?? $this->getPhoneNumber($notifiable);

        if (empty($to)) {
            throw new \InvalidArgumentException(
                'SMS notification requires a recipient phone number'
            );
        }

        // Send the SMS via the provider
        return $this->smsProvider->send($to, $message->getContent() ?? '');
    }

    /**
     * Get the phone number from the notifiable entity.
     */
    protected function getPhoneNumber(mixed $notifiable): ?string
    {
        // Check for routeNotificationForSms method
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms();
        }

        // Check for phone attribute
        if (isset($notifiable->phone)) {
            return $notifiable->phone;
        }

        // Check for phone_number attribute
        if (isset($notifiable->phone_number)) {
            return $notifiable->phone_number;
        }

        // Check for mobile attribute
        if (isset($notifiable->mobile)) {
            return $notifiable->mobile;
        }

        return null;
    }

    /**
     * Convert an array to SmsMessage instance.
     */
    protected function arrayToSmsMessage(array $data): SmsMessage
    {
        $message = new SmsMessage;

        if (isset($data['to'])) {
            $message->to($data['to']);
        }

        if (isset($data['content'])) {
            $message->content($data['content']);
        }

        if (isset($data['from'])) {
            $message->from($data['from']);
        }

        return $message;
    }
}
