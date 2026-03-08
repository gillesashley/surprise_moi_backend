<?php

namespace Tests\Feature\Notifications;

use App\Models\DeviceToken;
use App\Models\VendorApplication;
use App\Notifications\VendorApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\BroadcastChannel;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

class VendorApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_database_mail_and_broadcast_channels(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');

        $channels = $notification->via($vendorApplication->user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(BroadcastChannel::class, $channels);
    }

    public function test_to_database_returns_correct_shape_for_approved(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');
        $data = $notification->toDatabase($user);

        $this->assertSame('vendor_approved', $data['type']);
        $this->assertSame('Application Approved', $data['title']);
        $this->assertSame('Your vendor application has been approved.', $data['message']);
        $this->assertSame('/dashboard/vendor-applications/'.$vendorApplication->id, $data['action_url']);
        $this->assertNull($data['actor']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($vendorApplication->id, $data['subject']['id']);
        $this->assertSame('vendor_application', $data['subject']['type']);
        $this->assertSame('approved', $data['subject']['status']);
    }

    public function test_to_database_returns_correct_shape_for_rejected(): void
    {
        $vendorApplication = VendorApplication::factory()->rejected()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApprovalNotification($vendorApplication, 'rejected');
        $data = $notification->toDatabase($user);

        $this->assertSame('vendor_rejected', $data['type']);
        $this->assertSame('Application Rejected', $data['title']);
        $this->assertSame('Your vendor application has been rejected.', $data['message']);
        $this->assertSame('/dashboard/vendor-applications/'.$vendorApplication->id, $data['action_url']);
        $this->assertNull($data['actor']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($vendorApplication->id, $data['subject']['id']);
        $this->assertSame('vendor_application', $data['subject']['type']);
        $this->assertSame('rejected', $data['subject']['status']);
    }

    public function test_notification_defaults_to_approved_status(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApprovalNotification($vendorApplication);
        $data = $notification->toDatabase($vendorApplication->user);

        $this->assertSame('vendor_approved', $data['type']);
        $this->assertSame('Application Approved', $data['title']);
    }

    public function test_notification_is_queued_on_notifications_queue(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');

        $this->assertSame('notifications', $notification->queue);
    }

    public function test_via_includes_fcm_channel_when_user_has_device_tokens(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fake-fcm-token',
            'device_name' => 'Test Device',
            'platform' => 'android',
        ]);

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');

        $channels = $notification->via($user);

        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_via_excludes_fcm_channel_when_user_has_no_device_tokens(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');

        $channels = $notification->via($vendorApplication->user);

        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_to_fcm_returns_correct_message_for_approved(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApprovalNotification($vendorApplication, 'approved');
        $fcmMessage = $notification->toFcm($user);

        $this->assertInstanceOf(FcmMessage::class, $fcmMessage);
    }

    public function test_to_fcm_returns_correct_message_for_rejected(): void
    {
        $vendorApplication = VendorApplication::factory()->rejected()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApprovalNotification($vendorApplication, 'rejected');
        $fcmMessage = $notification->toFcm($user);

        $this->assertInstanceOf(FcmMessage::class, $fcmMessage);
    }
}
