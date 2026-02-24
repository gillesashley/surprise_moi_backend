<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

class SendPasswordResetEmail extends BaseJob
{
    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $resetToken;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, string $resetToken)
    {
        $this->user = $user;
        $this->resetToken = $resetToken;

        // Use emails queue by default
        parent::__construct('emails');
    }

    /**
     * Get the default queue for this job type.
     */
    protected function getDefaultQueue(): string
    {
        return 'emails';
    }

    /**
     * Execute the actual job logic.
     */
    public function executeJob(): void
    {
        // Generate password reset URL
        $resetUrl = $this->generateResetUrl();

        // Create the mailable
        $mailable = new PasswordReset($this->user, $resetUrl, $this->resetToken);

        // Send the email using the SendEmail job
        dispatch(new SendEmail($this->user->email, $mailable));
    }

    /**
     * Generate the password reset URL.
     */
    public function generateResetUrl(): string
    {
        // Use Laravel's built-in password reset URL generation
        return URL::route('password.reset', [
            'token' => $this->resetToken,
            'email' => $this->user->email,
        ]);
    }

    /**
     * Get job data for logging (mask sensitive data).
     */
    protected function getJobDataForLogging(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_email' => $this->maskEmail($this->user->email),
            'user_name' => $this->user->name ?? 'N/A',
            'reset_token' => $this->maskToken($this->resetToken),
        ];
    }

    /**
     * Mask email address for logging (show only domain for privacy).
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $username = $parts[0];
        $domain = $parts[1];

        // Show only first 2 characters of username and the domain
        $maskedUsername = strlen($username) > 2
            ? substr($username, 0, 2).'***'
            : '***';

        return $maskedUsername.'@'.$domain;
    }

    /**
     * Mask reset token for logging (security).
     */
    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 6) {
            return '***';
        }

        // Show first 3 characters and mask the rest
        return substr($token, 0, 3).'***';
    }

    /**
     * Get the display name of the job.
     */
    public function getDisplayName(): string
    {
        return 'SendPasswordResetEmail';
    }

    /**
     * Handle job-specific failure logic.
     */
    protected function handleFailure(\Throwable $exception): void
    {
        // Log specific failure for password reset email
        // Could implement user notification about failed password reset email here

        // For now, just let the BaseJob handle the logging
        parent::handleFailure($exception);
    }
}
