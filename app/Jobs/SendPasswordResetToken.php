<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPasswordResetToken extends BaseJob
{
    /**
     * The user requesting password reset.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The user's email address.
     *
     * @var string
     */
    public $email;

    /**
     * Create a new job instance.
     *
     * @param User $user
     * @param string $email
     * @return void
     */
    public function __construct(User $user, string $email)
    {
        $this->user = $user;
        $this->email = $email;

        parent::__construct('tokens');
    }

    /**
     * Get the default queue for this job type.
     *
     * @return string
     */
    protected function getDefaultQueue(): string
    {
        return 'tokens';
    }

    /**
     * Execute the actual job logic.
     *
     * @return void
     */
    public function executeJob(): void
    {
        $resetToken = Password::createToken($this->user);

        dispatch(new SendPasswordResetEmail($this->user, $resetToken));
    }

    /**
     * Get job data for logging (mask sensitive data).
     *
     * @return array
     */
    protected function getJobDataForLogging(): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->maskEmail($this->email),
        ];
    }

    /**
     * Mask email address for logging (show only domain for privacy).
     *
     * @param string $email
     * @return string
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $username = $parts[0];
        $domain = $parts[1];

        $maskedUsername = strlen($username) > 2
            ? substr($username, 0, 2) . '***'
            : '***';

        return $maskedUsername . '@' . $domain;
    }

    /**
     * Handle job-specific failure logic.
     *
     * @param Throwable $exception
     * @return void
     */
    protected function handleFailure(Throwable $exception): void
    {
        Log::error('Password reset token job failed', [
            'job_class' => static::class,
            'user_id' => $this->user->id,
            'email' => $this->maskEmail($this->email),
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the display name of the job.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return 'SendPasswordResetToken';
    }
}
