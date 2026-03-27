<?php

namespace App\Console\Commands;

use App\Mail\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

class EmailDatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:email';

    /**
     * The console command description.
     */
    protected $description = 'Create a database backup and email it';

    /**
     * Tables to exclude from the backup.
     *
     * @var array<int, string>
     */
    protected array $excludeTables = [
        'migrations',
        'password_reset_tokens',
        'sessions',
        'failed_jobs',
        'job_batches',
        'jobs',
        'cache',
        'cache_locks',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        $recipient = config('backup.notification_email');

        if (empty($recipient)) {
            $this->error('BACKUP_NOTIFICATION_EMAIL is not configured.');
            Log::error('Database backup failed: BACKUP_NOTIFICATION_EMAIL is not set.');

            return Command::FAILURE;
        }

        $dbConfig = config('database.connections.pgsql');
        $dbName = $dbConfig['database'];
        $dbUser = $dbConfig['username'];
        $dbPassword = $dbConfig['password'];
        $dbHost = $dbConfig['host'];
        $dbPort = $dbConfig['port'];

        $timestamp = now()->format('Ymd_His');
        $filename = "surprisemoi_backup_{$timestamp}.sql.gz";
        $tempDir = storage_path('app/temp');
        $filePath = "{$tempDir}/{$filename}";

        try {
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $excludeArgs = collect($this->excludeTables)
                ->map(fn (string $table): string => "--exclude-table={$table}")
                ->implode(' ');

            $command = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s %s %s | gzip > %s',
                escapeshellarg($dbPassword),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                $excludeArgs,
                escapeshellarg($dbName),
                escapeshellarg($filePath),
            );

            $result = Process::timeout(300)->run($command);

            if (! file_exists($filePath) || filesize($filePath) === 0) {
                $this->error('Backup file was not created or is empty.');
                Log::error('Database backup failed: file not created.', [
                    'stderr' => $result->errorOutput(),
                ]);

                return Command::FAILURE;
            }

            $fileSize = $this->formatFileSize(filesize($filePath));

            $this->info("Backup created: {$filename} ({$fileSize})");

            Mail::to($recipient)->send(new DatabaseBackup(
                filePath: $filePath,
                database: $dbName,
                fileSize: $fileSize,
                filename: $filename,
            ));

            $this->info("Backup emailed to {$recipient}");
            Log::info('Database backup emailed successfully.', [
                'recipient' => $recipient,
                'filename' => $filename,
                'size' => $fileSize,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Backup failed: '.$e->getMessage());
            Log::error('Database backup failed: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
