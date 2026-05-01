<?php

use App\Services\TargetService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks
Schedule::call(function (TargetService $targetService) {
    $expired = $targetService->expireTargets();
    logger()->info("Expired {$expired} targets");
})->daily()->at('00:05');

Schedule::job(new \App\Jobs\ReleasePendingFundsJob)->hourly();

Schedule::job(new \App\Jobs\VerifyPendingTransfersJob)->everyThirtyMinutes();

Schedule::command('tier-upgrade:expire-stale')->hourly();

// Email database backup daily at midnight
Schedule::command('backup:email')->daily()->at('00:00');

// Audit log retention: prune standard-retention rows older than 90 days.
Schedule::command('audit:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// Vendor application flag deadlines: send reminders before deadline, alert admins after.
Schedule::command('vendor-applications:process-flag-deadlines')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
