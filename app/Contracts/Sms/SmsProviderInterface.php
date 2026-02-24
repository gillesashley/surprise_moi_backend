<?php

namespace App\Contracts\Sms;

/**
 * Interface for SMS provider implementations.
 *
 * This interface defines the contract that all SMS providers must implement,
 * allowing for easy swapping of SMS providers without changing the calling code.
 */
interface SmsProviderInterface
{
    /**
     * Send a generic SMS message to a phone number.
     *
     * @param  string  $to  The recipient phone number
     * @param  string  $message  The message content
     * @return array{success: bool, message: string, data: array|null}
     */
    public function send(string $to, string $message): array;

    /**
     * Send an OTP to a phone number.
     *
     * @param  string  $phoneNumber  The recipient phone number
     * @param  string|null  $message  Custom message template
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendOtp(string $phoneNumber, ?string $message = null): array;

    /**
     * Validate an OTP code.
     *
     * @param  string  $code  The OTP code to validate
     * @param  string  $phoneNumber  The recipient phone number
     * @return array{success: bool, message: string, data: array|null}
     */
    public function validateOtp(string $code, string $phoneNumber): array;
}
