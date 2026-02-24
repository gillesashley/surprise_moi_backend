<?php

namespace App\Jobs;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class SendEmail extends BaseJob
{
    /**
     * The email recipient.
     *
     * @var string
     */
    public $recipient;

    /**
     * The mailable instance.
     *
     * @var \Illuminate\Mail\Mailable
     */
    public $mailable;

    /**
     * The email subject.
     *
     * @var string
     */
    public $subject;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $recipient, Mailable $mailable, ?string $subject = null)
    {
        $this->recipient = $recipient;
        $this->mailable = $mailable;
        $this->subject = $subject;

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
        Mail::to($this->recipient)->send($this->mailable);
    }

    /**
     * Get job data for logging (mask sensitive data).
     */
    protected function getJobDataForLogging(): array
    {
        return [
            'recipient' => $this->maskEmail($this->recipient),
            'mailable_class' => get_class($this->mailable),
            'subject' => $this->subject,
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
        return 'SendEmail';
    }
}
