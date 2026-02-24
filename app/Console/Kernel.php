<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily database backup at 2:00 AM with GFS cleanup
        $schedule->command('backup:run')
            ->dailyAt('02:00')
            ->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'));

        // Cleanup old backups (run daily at 2:30 AM)
        $schedule->command('backup:cleanup')
            ->dailyAt('02:30')
            ->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'));

        // Optional: Test backup every hour (comment out in production)
        // $schedule->command('backup:run')->hourly()->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
