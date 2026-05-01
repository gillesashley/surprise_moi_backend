<?php

namespace Tests\Feature\Notifications;

use App\Channels\SmsChannel;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlaggedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class VendorFlaggedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_database_mail_broadcast_and_sms_when_phone_present(): void
    {
        $user = User::factory()->create(['phone' => '+233244000000']);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(BroadcastChannel::class, $channels);
        $this->assertContains(SmsChannel::class, $channels);
        // FCM only fires when a device token exists; this user has none.
        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_via_excludes_sms_when_phone_missing(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertNotContains(SmsChannel::class, $channels);
    }

    public function test_via_includes_fcm_when_device_token_present(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'fake-token', 'platform' => 'android']);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_to_database_returns_correct_shape(): void
    {
        $user = User::factory()->create();
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $data = (new VendorFlaggedNotification($app))->toDatabase($user);

        $this->assertSame('vendor_flagged', $data['type']);
        $this->assertSame('Action Required on Your Vendor Application', $data['title']);
        $this->assertStringContainsString('more details', $data['message']);
        $this->assertSame('/dashboard/vendor-applications/'.$app->id, $data['action_url']);
        $this->assertSame('flagged', $data['subject']['status']);
    }

    public function test_to_mail_includes_flag_reason_and_deadline(): void
    {
        $user = User::factory()->create();
        $app = VendorApplication::factory()->for($user)->flagged()->create([
            'flag_reason' => 'Ghana card back image is unreadable',
            'grace_period_ends_at' => now()->addDays(7),
        ]);

        $mail = (new VendorFlaggedNotification($app))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Action Required: Update Your Vendor Application', $mail->subject);
        $allLines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('Ghana card back image is unreadable', $allLines);
    }

    public function test_to_sms_fits_in_a_single_segment_with_a_long_reason(): void
    {
        // GSM-7 single-segment SMS limit is 160 chars. A long reason must be
        // truncated so total content fits — exceeding 160 splits into two
        // chargeable segments and risks concat issues on older handsets.
        $user = User::factory()->create();
        $longReason = str_repeat('Ghana card back image is unreadable. ', 5); // ~185 chars
        $app = VendorApplication::factory()->for($user)->flagged()->create([
            'flag_reason' => $longReason,
            'grace_period_ends_at' => now()->addDays(7),
        ]);

        $sms = (new VendorFlaggedNotification($app))->toSms($user);

        $this->assertNotNull($sms->getContent());
        $this->assertStringContainsString('Surprise moi', $sms->getContent());
        $this->assertLessThanOrEqual(160, mb_strlen($sms->getContent()));
    }

    public function test_notification_is_queued_on_notifications_queue(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $notification = new VendorFlaggedNotification($app);

        $this->assertSame('notifications', $notification->queue);
    }
}
