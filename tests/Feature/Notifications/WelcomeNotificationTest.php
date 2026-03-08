<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_has_welcome_subject(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Welcome to Surprise Moi! Verify Your Email', $mail->subject);
    }

    public function test_verification_email_contains_user_name_in_greeting(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertSame('Hello Jane Doe,', $mail->greeting);
    }

    public function test_verification_email_contains_welcome_message(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertContains(
            'Your Surprise Moi account has been created successfully. Let the surprises begin!',
            $mail->introLines
        );
    }

    public function test_verification_email_contains_verify_prompt(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertContains(
            'Please click the button below to verify your email address and get started.',
            $mail->introLines
        );
    }

    public function test_verification_email_has_verify_action_button(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertSame('Verify Email Address', $mail->actionText);
        $this->assertNotEmpty($mail->actionUrl);
    }
}
