<?php

namespace Tests\Feature;

use App\Mail\AccountDeletionConfirmation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_submit_does_not_delete_user_immediately(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'someone@example.com']);

        $response = $this->post(route('account-deletion.submit'), [
            'email' => 'someone@example.com',
        ]);

        $response->assertRedirect(route('account-deletion.show'))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        Mail::assertQueued(
            AccountDeletionConfirmation::class,
            fn ($mail) => $mail->hasTo('someone@example.com') && $mail->user->is($user)
        );
    }

    public function test_submit_returns_generic_response_for_unknown_email(): void
    {
        Mail::fake();

        $response = $this->post(route('account-deletion.submit'), [
            'email' => 'noone@example.com',
        ]);

        $response->assertRedirect(route('account-deletion.show'))
            ->assertSessionHas('status');
        Mail::assertNothingQueued();
    }

    public function test_submit_validates_email_format(): void
    {
        $response = $this->post(route('account-deletion.submit'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_show_confirmation_with_valid_signature_renders_page(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addMinutes(60),
            ['user' => $user->id]
        );

        $response = $this->get($url);

        $response->assertOk()
            ->assertSee('Confirm Account Deletion')
            ->assertSee($user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_show_confirmation_with_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/account-deletion/confirm/'.$user->id);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_show_confirmation_with_expired_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->subMinute(),
            ['user' => $user->id]
        );

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_confirm_with_valid_signature_deletes_user(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addMinutes(60),
            ['user' => $user->id]
        );

        $response = $this->post($url);

        $response->assertOk()
            ->assertSee('Account Deleted');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_confirm_without_signature_does_not_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/account-deletion/confirm/'.$user->id);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_confirm_with_tampered_user_id_is_rejected(): void
    {
        $victim = User::factory()->create();
        $attackerTarget = User::factory()->create();

        $signedForVictim = URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addMinutes(60),
            ['user' => $victim->id]
        );

        $tampered = str_replace(
            '/account-deletion/confirm/'.$victim->id,
            '/account-deletion/confirm/'.$attackerTarget->id,
            $signedForVictim
        );

        $response = $this->post($tampered);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
        $this->assertDatabaseHas('users', ['id' => $attackerTarget->id]);
    }
}
