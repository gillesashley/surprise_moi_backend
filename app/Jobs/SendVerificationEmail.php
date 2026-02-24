<?php

namespace App\Jobs;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class SendVerificationEmail extends BaseJob
{
    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;

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
        // Generate verification URL
        $verificationUrl = $this->generateVerificationUrl();

        // Create the mailable
        $mailable = new EmailVerification($this->user, $verificationUrl);

        // Send the email using the SendEmail job
        dispatch(new SendEmail($this->user->email, $mailable));
    }

    /**
     * Generate the email verification URL.
     */
    public function generateVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );
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
     * Get the display name of the job.
     */
    public function getDisplayName(): string
    {
        return 'SendVerificationEmail';
    }

    /**
     * Handle job-specific failure logic.
     */
    protected function handleFailure(\Throwable $exception): void
    {
        // Log specific failure for verification email
        // Could implement user notification about failed verification email here

        // For now, just let the BaseJob handle the logging
        parent::handleFailure($exception);
    }
}
