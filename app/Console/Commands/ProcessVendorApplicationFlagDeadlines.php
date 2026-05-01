<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlagExpiredNotification;
use App\Notifications\VendorFlagReminderNotification;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class ProcessVendorApplicationFlagDeadlines extends Command
{
    protected $signature = 'vendor-applications:process-flag-deadlines';

    protected $description = 'Send pre-deadline reminders to vendors and post-deadline alerts to admins for flagged vendor applications.';

    public function handle(AuditService $auditService): int
    {
        $reminders = $this->sendReminders($auditService);
        $expired = $this->sendExpiredAlerts($auditService);

        $this->info("Reminders sent: {$reminders}. Expired alerts sent: {$expired}.");

        return self::SUCCESS;
    }

    protected function sendReminders(AuditService $auditService): int
    {
        $reminderDays = (int) Setting::get('vendor_application_flag_reminder_days_before', 2);
        $count = 0;

        VendorApplication::query()
            ->flagged()
            ->whereNull('flag_reminder_sent_at')
            ->where('grace_period_ends_at', '>', now())
            ->where('grace_period_ends_at', '<=', now()->addDays($reminderDays))
            ->with('user')
            ->chunkById(50, function ($apps) use ($auditService, $reminderDays, &$count) {
                foreach ($apps as $app) {
                    if ($app->user) {
                        $app->user->notify(new VendorFlagReminderNotification($app));
                    }
                    $app->update(['flag_reminder_sent_at' => now()]);

                    $auditService->record(
                        'vendor_application.flag_reminder_sent',
                        $app,
                        null,
                        extra: ['days_before_deadline' => $reminderDays],
                        retentionClass: 'standard'
                    );

                    $count++;
                }
            });

        return $count;
    }

    protected function sendExpiredAlerts(AuditService $auditService): int
    {
        // Fetched once before chunking so all expired applications in this run notify
        // the same admin set. Admins added during a run will be included on the next
        // daily execution.
        $admins = User::admins()->get();
        $count = 0;

        VendorApplication::query()
            ->flagged()
            ->whereNull('flag_expired_alert_sent_at')
            ->where('grace_period_ends_at', '<', now())
            ->chunkById(50, function ($apps) use ($admins, $auditService, &$count) {
                foreach ($apps as $app) {
                    if ($admins->isNotEmpty()) {
                        Notification::send($admins, new VendorFlagExpiredNotification($app));
                    }
                    $app->update(['flag_expired_alert_sent_at' => now()]);

                    $auditService->record(
                        'vendor_application.flag_expired_alert_sent',
                        $app,
                        null,
                        extra: [
                            'deadline' => $app->grace_period_ends_at?->toIso8601String(),
                            'admins_notified_count' => $admins->count(),
                        ],
                        retentionClass: 'standard'
                    );

                    $count++;
                }
            });

        return $count;
    }
}
