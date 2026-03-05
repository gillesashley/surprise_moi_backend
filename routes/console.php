<?php

use App\Services\ReferralService;
use App\Services\TargetService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks
Schedule::call(function (ReferralService $referralService) {
    $expired = $referralService->expireCommissions();
    logger()->info("Expired {$expired} referral commissions");
})->daily()->at('00:00');

Schedule::call(function (TargetService $targetService) {
    $expired = $targetService->expireTargets();
    logger()->info("Expired {$expired} targets");
})->daily()->at('00:05');

// Database backup with GFS retention policy
Schedule::command('backup:run')
    ->daily()
    ->at('02:00')
    ->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'));

// Cleanup old backups (GFS retention policy)
Schedule::command('backup:cleanup')
    ->daily()
    ->at('02:30')
    ->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'));

Schedule::job(new \App\Jobs\ReleasePendingFundsJob)->hourly();

Schedule::job(new \App\Jobs\VerifyPendingTransfersJob)->everyThirtyMinutes();
