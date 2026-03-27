<?php

namespace Tests\Feature;

use App\Mail\DatabaseBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EmailDatabaseBackupCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.notification_email' => 'test@example.com']);
    }

    public function test_it_fails_when_notification_email_is_not_configured(): void
    {
        config(['backup.notification_email' => null]);

        $this->artisan('backup:email')
            ->expectsOutputToContain('BACKUP_NOTIFICATION_EMAIL is not configured')
            ->assertFailed();
    }

    public function test_it_creates_backup_and_sends_email(): void
    {
        Mail::fake();

        // Freeze time so the filename is predictable
        $this->travelTo(Carbon::create(2026, 3, 27, 0, 0, 0));

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = 'surprisemoi_backup_20260327_000000.sql.gz';
        $filePath = "{$tempDir}/{$filename}";

        // Pre-create the backup file since pg_dump is faked
        file_put_contents($filePath, gzencode('-- fake backup data'));

        Process::fake([
            '*' => Process::result(output: '', errorOutput: ''),
        ]);

        $this->artisan('backup:email')
            ->expectsOutputToContain('Backup emailed to')
            ->assertSuccessful();

        Mail::assertSent(DatabaseBackup::class, function (DatabaseBackup $mail) {
            return $mail->hasTo('test@example.com')
                && str_contains($mail->filename, 'surprisemoi_backup_')
                && $mail->database === config('database.connections.pgsql.database');
        });

        // Verify temp file was cleaned up
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_it_fails_when_backup_file_is_not_created(): void
    {
        Mail::fake();

        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'pg_dump: error'),
        ]);

        $this->artisan('backup:email')
            ->expectsOutputToContain('Backup file was not created or is empty')
            ->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_it_cleans_up_temp_file_on_mail_failure(): void
    {
        // Freeze time so the filename is predictable
        $this->travelTo(Carbon::create(2026, 3, 27, 0, 0, 0));

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filePath = "{$tempDir}/surprisemoi_backup_20260327_000000.sql.gz";
        file_put_contents($filePath, gzencode('-- fake backup data'));

        Process::fake([
            '*' => Process::result(output: '', errorOutput: ''),
        ]);

        // Make Mail::to()->send() throw an exception
        Mail::shouldReceive('to')
            ->andThrow(new \Exception('SMTP connection failed'));

        $this->artisan('backup:email')
            ->assertFailed();

        // Temp file should be cleaned up even on failure
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_backup_is_scheduled_daily_at_midnight(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command ?? '', 'backup:email');
        });

        $this->assertTrue($events->isNotEmpty(), 'backup:email command is not scheduled');

        $event = $events->first();
        $this->assertEquals('0 0 * * *', $event->expression);
    }
}
