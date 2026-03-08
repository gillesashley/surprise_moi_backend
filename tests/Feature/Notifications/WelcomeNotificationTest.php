<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_only_mail_channel(): void
    {
        $user = User::factory()->create();

        $notification = new WelcomeNotification;

        $this->assertSame(['mail'], $notification->via($user));
    }

    public function test_to_mail_has_correct_subject(): void
    {
        $user = User::factory()->create();

        $notification = new WelcomeNotification;
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Welcome to Surprise Moi!', $mail->subject);
    }

    public function test_to_mail_contains_user_name_in_greeting(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $notification = new WelcomeNotification;
        $mail = $notification->toMail($user);

        $this->assertSame('Hello Jane Doe,', $mail->greeting);
    }

    public function test_to_mail_has_correct_action_url(): void
    {
        $user = User::factory()->create();

        $notification = new WelcomeNotification;
        $mail = $notification->toMail($user);

        $this->assertSame(config('deep_links.share_base_url'), $mail->actionUrl);
    }

    public function test_notification_is_queued_on_notifications_queue(): void
    {
        $notification = new WelcomeNotification;

        $this->assertSame('notifications', $notification->queue);
    }

    public function test_registered_event_dispatches_welcome_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        event(new Registered($user));

        Notification::assertSentTo($user, WelcomeNotification::class);
    }
}
