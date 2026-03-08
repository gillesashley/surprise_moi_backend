<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorApplicationSubmittedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_database_and_mail_channels(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);

        $channels = $notification->via($vendorApplication->user);

        $this->assertSame(['database', 'mail'], $channels);
    }

    public function test_via_does_not_include_broadcast_or_fcm(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);

        $channels = $notification->via($vendorApplication->user);

        $this->assertNotContains('broadcast', $channels);
        $this->assertCount(2, $channels);
    }

    public function test_to_database_returns_correct_shape(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);
        $data = $notification->toDatabase($user);

        $this->assertSame('vendor_application_submitted', $data['type']);
        $this->assertSame('Application Received', $data['title']);
        $this->assertSame('Your vendor registration has been received. We will review and notify you once approved.', $data['message']);
        $this->assertSame('/vendor-applications/'.$vendorApplication->id, $data['action_url']);
        $this->assertNull($data['actor']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($vendorApplication->id, $data['subject']['id']);
        $this->assertSame('vendor_application', $data['subject']['type']);
        $this->assertSame('pending', $data['subject']['status']);
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_to_mail_contains_vendor_name_in_greeting(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);
        $mail = $notification->toMail($user);

        $this->assertSame("Hello {$user->name},", $mail->greeting);
    }

    public function test_to_mail_has_correct_subject(): void
    {
        $vendorApplication = VendorApplication::factory()->create();
        $user = $vendorApplication->user;

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);
        $mail = $notification->toMail($user);

        $this->assertSame('Vendor Application Received', $mail->subject);
    }

    public function test_notification_is_queued_on_notifications_queue(): void
    {
        $vendorApplication = VendorApplication::factory()->create();

        $notification = new VendorApplicationSubmittedNotification($vendorApplication);

        $this->assertSame('notifications', $notification->queue);
    }

    public function test_submit_dispatches_notification_to_vendor(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $vendorApplication = VendorApplication::factory()
            ->for($user)
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->create();

        $vendorApplication->submit();

        Notification::assertSentTo(
            $user,
            VendorApplicationSubmittedNotification::class,
            function (VendorApplicationSubmittedNotification $notification) use ($vendorApplication) {
                return $notification->vendorApplication->id === $vendorApplication->id;
            }
        );
    }
}
