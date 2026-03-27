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

Schedule::job(new \App\Jobs\ReleasePendingFundsJob)->hourly();

Schedule::job(new \App\Jobs\VerifyPendingTransfersJob)->everyThirtyMinutes();

Schedule::command('tier-upgrade:expire-stale')->hourly();

// Email database backup daily at midnight
Schedule::command('backup:email')->daily()->at('00:00');
