<?php

namespace App\Services;

use App\Contracts\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class KairosAfrikaSmsService implements SmsProviderInterface
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $apiSecret;

    protected string $apiVersion;

    protected string $senderName;

    public function __construct()
    {
        $this->apiUrl = config('services.kairosafrika.api_url');
        $this->apiKey = config('services.kairosafrika.api_key');
        $this->apiSecret = config('services.kairosafrika.api_secret');
        $this->apiVersion = config('services.kairosafrika.api_version');
        $this->senderName = config('services.kairosafrika.sender_name', 'SurpriseMoi');
    }

    /**
     * Send OTP to a phone number.
     *
     * @param  string  $phoneNumber  The recipient phone number (e.g., '0559400612' or '233559400612')
     * @param  string|null  $message  Custom message template with {code}, {amount}, {duration} placeholders
     * @return array{success: bool, message: string, data: array|null}
     */
    public function sendOtp(string $phoneNumber, ?string $message = null): array
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        $message = $message ?? 'Your Surprise moi verification code is {code}. It expires in {amount} {duration}.';

        // Check if logging mode is enabled
        if (config('services.kairosafrika.log_only', false)) {
            Log::info('SMS logged (not sent)', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'message' => $message,
            ]);

            return [
                'success' => true,
                'message' => 'SMS logged successfully',
                'data' => [
                    'transactionId' => 'logged-only',
                ],
            ];
        }

        try {
            $http = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'x-api-secret' => $this->apiSecret,
                'x-api-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ]);

            // Disable SSL verification in local environment only
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$this->apiUrl}/external/generate/otp", [
                'recipient' => $phoneNumber,
                'from' => $this->senderName,
                'message' => $message,
                'pinLength' => 4,
                'pinType' => 'NUMERIC',
                'expiry' => [
                    'amount' => 10,
                    'duration' => 'minutes',
                ],
                'maxAmountOfValidationRetries' => 3,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['statusCode'] ?? null) === '200') {
                Log::info('OTP sent successfully', [
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                    'transactionId' => $data['transactionId'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => [
                        'transactionId' => $data['transactionId'] ?? null,
                        'uuid' => $data['data']['uuid'] ?? null,
                    ],
                ];
            }

            Log::warning('Failed to send OTP', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['statusMessage'] ?? 'Failed to send OTP',
                'data' => null,
            ];
        } catch (\Exception $e) {
            Log::error('OTP service error', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service temporarily unavailable',
                'data' => null,
            ];
        }
    }

    /**
     * Validate OTP code.
     *
     * @param  string  $code  The OTP code entered by user
     * @param  string  $phoneNumber  The recipient phone number
     * @return array{success: bool, message: string, data: array|null}
     */
    public function validateOtp(string $code, string $phoneNumber): array
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        try {
            $http = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'x-api-secret' => $this->apiSecret,
                'x-api-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ]);

            // Disable SSL verification in local environment only
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$this->apiUrl}/external/validate/otp", [
                'code' => $code,
                'recipient' => $phoneNumber,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['statusCode'] ?? null) === '200') {
                Log::info('OTP validated successfully', [
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                ]);

                return [
                    'success' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'transactionId' => $data['transactionId'] ?? null,
                    ],
                ];
            }

            Log::warning('OTP validation failed', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['statusMessage'] ?? 'Invalid or expired OTP',
                'data' => null,
            ];
        } catch (\Exception $e) {
            Log::error('OTP validation error', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service temporarily unavailable',
                'data' => null,
            ];
        }
    }

    /**
     * Send a generic SMS message.
     *
     * @param  string  $to  The recipient phone number
     * @param  string  $message  The message content
     * @return array{success: bool, message: string, data: array|null}
     */
    public function send(string $to, string $message): array
    {
        $phoneNumber = $this->formatPhoneNumber($to);

        // Check if logging mode is enabled
        if (config('services.kairosafrika.log_only', false)) {
            Log::info('SMS logged (not sent)', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'message' => $message,
            ]);

            return [
                'success' => true,
                'message' => 'SMS logged successfully',
                'data' => [
                    'transactionId' => 'logged-only',
                ],
            ];
        }

        try {
            $http = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'x-api-secret' => $this->apiSecret,
                'x-api-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ]);

            // Disable SSL verification in local environment only
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$this->apiUrl}/external/sms/quick", [
                'to' => $phoneNumber,
                'from' => $this->senderName,
                'message' => $message,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['statusCode'] ?? null) === '200') {
                Log::info('SMS sent successfully', [
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                    'transactionId' => $data['transactionId'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => [
                        'transactionId' => $data['transactionId'] ?? null,
                        'uuid' => $data['data']['uuid'] ?? null,
                    ],
                ];
            }

            Log::warning('Failed to send SMS', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['statusMessage'] ?? 'Failed to send SMS',
                'data' => null,
            ];
        } catch (\Exception $e) {
            Log::error('SMS service error', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service temporarily unavailable',
                'data' => null,
            ];
        }
    }

    /**
     * Normalize a phone number to the digits-only international format Kairos
     * expects (e.g. "233247648200"). Country-agnostic: parses via libphonenumber
     * and falls back to Ghana when no country code is present in the input.
     *
     * @throws InvalidArgumentException when the input cannot be parsed as a
     *                                  valid phone number.
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        try {
            $e164 = (new PhoneNumber($phoneNumber, ['GH']))->formatE164();
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Unable to parse phone number for SMS dispatch: {$phoneNumber}",
                previous: $e,
            );
        }

        return ltrim($e164, '+');
    }

    /**
     * Mask phone number for logging (privacy).
     */
    protected function maskPhoneNumber(string $phoneNumber): string
    {
        $length = strlen($phoneNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phoneNumber, 0, 3).str_repeat('*', $length - 6).substr($phoneNumber, -3);
    }
}
