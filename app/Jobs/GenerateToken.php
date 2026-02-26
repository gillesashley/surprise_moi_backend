<?php

namespace App\Jobs;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateToken extends BaseJob
{
    /**
     * The token types supported.
     */
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_API_TOKEN = 'api_token';
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_REFRESH_TOKEN = 'refresh_token';

    /**
     * The user ID for token generation.
     *
     * @var int
     */
    public $userId;

    /**
     * The type of token to generate.
     *
     * @var string
     */
    public $tokenType;

    /**
     * Additional metadata for token generation.
     *
     * @var array
     */
    public $metadata;

    /**
     * The generated token (set after job completion).
     *
     * @var string|null
     */
    public $generatedToken = null;

    /**
     * Create a new job instance.
     *
     * @param int $userId
     * @param string $tokenType
     * @param array $metadata
     * @return void
     */
    public function __construct(int $userId, string $tokenType, array $metadata = [])
    {
        $this->userId = $userId;
        $this->tokenType = $tokenType;
        $this->metadata = $metadata;

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
        $this->generatedToken = match ($this->tokenType) {
            self::TYPE_PASSWORD_RESET => $this->generatePasswordResetToken(),
            self::TYPE_API_TOKEN => $this->generateApiToken(),
            self::TYPE_EMAIL_VERIFICATION => $this->generateEmailVerificationToken(),
            self::TYPE_REFRESH_TOKEN => $this->generateRefreshToken(),
            default => throw new \InvalidArgumentException("Invalid token type: {$this->tokenType}"),
        };

        $this->logTokenGenerated();
    }

    /**
     * Generate a password reset token.
     *
     * @return string
     */
    protected function generatePasswordResetToken(): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->metadata['email'] ?? null],
            [
                'token' => hash('sha256', $token),
                'created_at' => now(),
            ]
        );

        return $token;
    }

    /**
     * Generate an API token.
     *
     * @return string
     */
    protected function generateApiToken(): string
    {
        $token = Str::random(80);

        if (isset($this->metadata['user'])) {
            $this->metadata['user']->tokens()->create([
                'name' => $this->metadata['name'] ?? 'api-token',
                'token' => hash('sha256', $token),
                'abilities' => $this->metadata['abilities'] ?? ['*'],
                'expires_at' => $this->metadata['expires_at'] ?? null,
            ]);
        }

        return $token;
    }

    /**
     * Generate an email verification token.
     *
     * @return string
     */
    protected function generateEmailVerificationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Generate a refresh token.
     *
     * @return string
     */
    protected function generateRefreshToken(): string
    {
        return Str::random(80);
    }

    /**
     * Get job data for logging (mask sensitive data).
     *
     * @return array
     */
    protected function getJobDataForLogging(): array
    {
        return [
            'user_id' => $this->userId,
            'token_type' => $this->tokenType,
            'has_token' => $this->generatedToken !== null,
            'metadata_keys' => array_keys($this->metadata),
        ];
    }

    /**
     * Log token generation.
     *
     * @return void
     */
    protected function logTokenGenerated(): void
    {
        Log::info('Token generated successfully', [
            'job_class' => static::class,
            'queue' => $this->queue,
            'user_id' => $this->userId,
            'token_type' => $this->tokenType,
            'token_masked' => $this->generatedToken ? $this->maskToken($this->generatedToken) : null,
        ]);
    }

    /**
     * Mask token for logging (security).
     *
     * @param string $token
     * @return string
     */
    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 6) {
            return '***';
        }

        return substr($token, 0, 3) . '***' . substr($token, -3);
    }

    /**
     * Get the generated token.
     *
     * @return string|null
     */
    public function getGeneratedToken(): ?string
    {
        return $this->generatedToken;
    }

    /**
     * Get the display name of the job.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return 'GenerateToken';
    }

    /**
     * Handle job-specific failure logic.
     *
     * @param Throwable $exception
     * @return void
     */
    protected function handleFailure(Throwable $exception): void
    {
        Log::error('Token generation failed', [
            'job_class' => static::class,
            'user_id' => $this->userId,
            'token_type' => $this->tokenType,
            'exception' => $exception->getMessage(),
        ]);
    }
}
