<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds a job should run.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 180, 300]; // Exponential backoff: 1min, 3min, 5min

    /**
     * The queue the job should be sent to.
     *
     * @var string
     */
    public $queue;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $queue = null)
    {
        $this->queue = $queue ?? $this->getDefaultQueue();
    }

    /**
     * Get the default queue for this job type.
     */
    protected function getDefaultQueue(): string
    {
        return 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->logJobStart();

        try {
            $this->executeJob();
            $this->logJobCompletion();
        } catch (Throwable $exception) {
            $this->logJobFailure($exception);
            throw $exception;
        }
    }

    /**
     * Execute the actual job logic.
     */
    abstract public function executeJob(): void;

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->logJobFailure($exception);

        // Additional failure handling can be implemented by child classes
        $this->handleFailure($exception);
    }

    /**
     * Log job failure with context.
     */
    protected function logJobFailure(Throwable $exception): void
    {
        Log::error('Job failed', [
            'job_class' => static::class,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_data' => $this->getJobDataForLogging(),
        ]);
    }

    /**
     * Get job data for logging (override in child classes to mask sensitive data).
     */
    protected function getJobDataForLogging(): array
    {
        return [];
    }

    /**
     * Handle job-specific failure logic (override in child classes).
     */
    protected function handleFailure(Throwable $exception): void
    {
        // Override in child classes for specific failure handling
    }

    /**
     * Log job start.
     */
    protected function logJobStart(): void
    {
        Log::info('Job started', [
            'job_class' => static::class,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'job_data' => $this->getJobDataForLogging(),
        ]);
    }

    /**
     * Log job completion.
     */
    protected function logJobCompletion(): void
    {
        Log::info('Job completed', [
            'job_class' => static::class,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Get the job's unique identifier.
     */
    public function getJobId(): ?string
    {
        return $this->job?->getJobId();
    }

    /**
     * Get the display name of the job.
     */
    public function getDisplayName(): string
    {
        return class_basename(static::class);
    }
}
