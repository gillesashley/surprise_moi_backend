<?php

namespace Tests\Feature\Notifications;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_verification_email_has_welcome_subject(): void
    {
        $user = User::factory()->create();

        $mailable = new EmailVerification($user, 'https://example.com/verify');

        $mailable->assertHasSubject('Welcome to Surprise Moi! Verify Your Email');
    }

    public function test_api_verification_email_contains_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $mailable = new EmailVerification($user, 'https://example.com/verify');

        $mailable->assertSeeInHtml('Hello Jane Doe,');
    }

    public function test_api_verification_email_contains_welcome_message(): void
    {
        $user = User::factory()->create();

        $mailable = new EmailVerification($user, 'https://example.com/verify');

        $mailable->assertSeeInHtml('Your Surprise Moi account has been created successfully. Let the surprises begin!');
    }

    public function test_api_verification_email_contains_verify_button(): void
    {
        $user = User::factory()->create();
        $verifyUrl = 'https://example.com/verify/123';

        $mailable = new EmailVerification($user, $verifyUrl);

        $mailable->assertSeeInHtml('Verify Email Address');
        $mailable->assertSeeInHtml($verifyUrl);
    }

    public function test_web_verification_email_has_welcome_subject(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Welcome to Surprise Moi! Verify Your Email', $mail->subject);
    }

    public function test_web_verification_email_contains_welcome_message(): void
    {
        $user = User::factory()->create();

        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->assertContains(
            'Your Surprise Moi account has been created successfully. Let the surprises begin!',
            $mail->introLines
        );
    }

    public function test_verify_endpoint_shows_success_page_with_deep_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('auth.email-verified');
        $response->assertSeeText('Email Verified!');
        $response->assertSee(config('deep_links.scheme').'://email-verified');
    }

    public function test_verify_endpoint_marks_email_as_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_endpoint_shows_already_verified_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSeeText('Already Verified');
        $response->assertSee(config('deep_links.scheme').'://email-verified');
    }

    public function test_verify_endpoint_shows_error_page_for_invalid_hash(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'invalid-hash']
        );

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertSeeText('Invalid Link');
    }
}
