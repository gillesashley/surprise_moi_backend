<?php

namespace Tests\Feature\Console;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlagExpiredNotification;
use App\Notifications\VendorFlagReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessVendorApplicationFlagDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_when_deadline_within_window(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDay(),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertSentTo($app->user, VendorFlagReminderNotification::class);
        $this->assertNotNull($app->fresh()->flag_reminder_sent_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'vendor_application.flag_reminder_sent',
            'subject_id' => $app->id,
        ]);
    }

    public function test_does_not_send_reminder_twice(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDay(),
            'flag_reminder_sent_at' => now()->subHour(),
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_does_not_send_reminder_if_deadline_passed(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subHour(),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_does_not_send_reminder_if_outside_window(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDays(5),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_sends_expired_alert_to_admins_after_deadline(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $vendor = User::factory()->create(['role' => 'vendor']); // should NOT receive

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subHour(),
            'flag_expired_alert_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertSentTo($admin, VendorFlagExpiredNotification::class);
        Notification::assertSentTo($superAdmin, VendorFlagExpiredNotification::class);
        Notification::assertNotSentTo($vendor, VendorFlagExpiredNotification::class);
        $this->assertNotNull($app->fresh()->flag_expired_alert_sent_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'vendor_application.flag_expired_alert_sent',
            'subject_id' => $app->id,
        ]);
    }

    public function test_does_not_send_expired_alert_twice(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subDay(),
            'flag_expired_alert_sent_at' => now()->subHour(),
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($admin, VendorFlagExpiredNotification::class);
    }

    public function test_skips_applications_whose_status_changed_after_flagging(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        // Vendor resubmitted; status moved off "flagged"
        VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_UNDER_REVIEW,
            'grace_period_ends_at' => now()->subHour(),
            'flag_expired_alert_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($admin, VendorFlagExpiredNotification::class);
    }
}
