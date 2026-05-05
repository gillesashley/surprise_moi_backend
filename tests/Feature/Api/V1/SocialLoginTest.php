<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/social-login';

    /**
     * Helper to fake a successful Google tokeninfo response.
     *
     * @param  array{sub?: string, email?: string, name?: string, picture?: string|null, aud?: string}  $overrides
     */
    private function fakeGoogleTokenSuccess(array $overrides = []): void
    {
        $payload = array_merge([
            'sub' => '110248495921238986420',
            'email' => 'john@gmail.com',
            'name' => 'John Doe',
            'picture' => 'https://lh3.googleusercontent.com/photo.jpg',
            'aud' => config('services.google.client_id'),
            'iss' => 'https://accounts.google.com',
            'email_verified' => 'true',
        ], $overrides);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($payload),
        ]);
    }

    /**
     * Helper to fake a failed Google tokeninfo response.
     */
    private function fakeGoogleTokenFailure(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response(
                ['error_description' => 'Invalid Value'],
                400
            ),
        ]);
    }

    /**
     * Helper to fake a successful Facebook debug_token response.
     *
     * @param  array{is_valid?: bool, app_id?: string, expires_at?: int, user_id?: string}  $overrides
     */
    private function fakeFacebookDebugTokenSuccess(array $overrides = []): array
    {
        $appId = config('services.facebook.app_id') ?: 'test-fb-app-id';

        $payload = array_merge([
            'is_valid' => true,
            'app_id' => $appId,
            'expires_at' => 0, // 0 = never expires
            'user_id' => '1234567890',
        ], $overrides);

        return ['data' => $payload];
    }

    /**
     * Helper to fake a successful Facebook /me profile response.
     *
     * @param  array{id?: string, name?: string, email?: string|null, picture?: array<string, mixed>|null}  $overrides
     */
    private function fakeFacebookMeProfile(array $overrides = []): array
    {
        return array_merge([
            'id' => '1234567890',
            'name' => 'Jane Doe',
            'email' => 'jane@facebook.com',
            'picture' => ['data' => ['url' => 'https://scontent.example/jane.jpg']],
        ], $overrides);
    }

    /**
     * Helper to set up Http::fake routes for both FB endpoints with given payloads.
     *
     * @param  array<string, mixed>|null  $debugTokenPayload  null = HTTP 500
     * @param  array<string, mixed>|null  $meProfilePayload   null = HTTP 500
     */
    private function fakeFacebookEndpoints(?array $debugTokenPayload, ?array $meProfilePayload): void
    {
        Http::fake([
            'graph.facebook.com/debug_token*' => $debugTokenPayload === null
                ? Http::response('', 500)
                : Http::response($debugTokenPayload),
            'graph.facebook.com/me*' => $meProfilePayload === null
                ? Http::response('', 500)
                : Http::response($meProfilePayload),
        ]);
    }

    public function test_social_login_creates_new_user_with_google(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $this->fakeGoogleTokenSuccess([
            'aud' => 'test-client-id.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'john@gmail.com')
            ->assertJsonPath('data.user.name', 'John Doe')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.user.is_new_user', true)
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user' => ['id', 'email', 'name', 'role', 'is_new_user']],
            ]);

        $this->assertNotNull($response->json('data.user.email_verified_at'));
        $this->assertDatabaseHas('users', ['email' => 'john@gmail.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_id' => '110248495921238986420',
            'provider_email' => 'john@gmail.com',
        ]);
    }

    public function test_social_login_assigns_vendor_role_when_requested(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $this->fakeGoogleTokenSuccess([
            'aud' => 'test-client-id.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
            'role' => 'vendor',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'vendor')
            ->assertJsonPath('data.user.is_new_user', true);
    }

    public function test_social_login_returns_existing_user_on_repeat_login(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $user = User::factory()->create(['email' => 'returning@gmail.com']);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_id' => '999888777',
            'provider_email' => 'returning@gmail.com',
        ]);

        $this->fakeGoogleTokenSuccess([
            'sub' => '999888777',
            'email' => 'returning@gmail.com',
            'name' => 'Returning User',
            'aud' => 'test-client-id.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.is_new_user', false);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_social_login_links_google_to_existing_email_user(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $user = User::factory()->create([
            'email' => 'existing@gmail.com',
            'name' => 'Existing User',
        ]);

        $this->fakeGoogleTokenSuccess([
            'sub' => '111222333',
            'email' => 'existing@gmail.com',
            'name' => 'Existing User',
            'aud' => 'test-client-id.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.is_new_user', false);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '111222333',
        ]);
    }

    public function test_social_login_returns_401_for_invalid_token(): void
    {
        $this->fakeGoogleTokenFailure();

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired token');
    }

    public function test_social_login_returns_401_for_audience_mismatch(): void
    {
        config()->set('services.google.client_id', 'my-real-client-id.apps.googleusercontent.com');

        $this->fakeGoogleTokenSuccess([
            'aud' => 'wrong-client-id.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-but-wrong-audience',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_social_login_returns_422_for_missing_provider(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'id_token' => 'some-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_social_login_returns_422_for_missing_token(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token']);
    }

    public function test_social_login_returns_422_for_unsupported_provider(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'twitter',
            'id_token' => 'some-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_social_login_validator_accepts_facebook_as_provider(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'any-token',
        ]);

        // Validator should pass; controller will reject with 401 because
        // services.facebook.app_id is empty (fail-closed). The point of this
        // test is that the response is NOT a 422 with a `provider` field error.
        $response->assertStatus(401);
        $response->assertJsonMissingValidationErrors(['provider']);
    }

    public function test_social_login_returns_422_for_invalid_role(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'some-token',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_social_login_rejects_when_client_id_not_configured(): void
    {
        config()->set('services.google.client_id', null);

        $this->fakeGoogleTokenSuccess([
            'aud' => 'any-audience.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_token_with_unverified_email(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $this->fakeGoogleTokenSuccess([
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'email_verified' => 'false',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'unverified-email-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_does_not_link_to_existing_user_when_email_unverified(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $victim = User::factory()->create(['email' => 'victim@gmail.com']);

        $this->fakeGoogleTokenSuccess([
            'sub' => 'attacker-google-sub',
            'email' => 'victim@gmail.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'email_verified' => 'false',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'unverified-email-token',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $victim->id,
            'provider' => 'google',
            'provider_id' => 'attacker-google-sub',
        ]);
    }

    public function test_social_login_rejects_token_with_invalid_issuer(): void
    {
        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');

        $this->fakeGoogleTokenSuccess([
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'iss' => 'https://impostor.example.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'wrong-issuer-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_when_facebook_app_id_not_configured(): void
    {
        config()->set('services.facebook.app_id', null);
        config()->set('services.facebook.app_secret', 'some-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(),
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'any-fb-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired token');

        // Critical: prove the fail-closed gate fires before network egress.
        Http::assertNothingSent();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_when_facebook_app_secret_not_configured(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', null);

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(),
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'any-fb-token',
        ]);

        $response->assertStatus(401);
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_creates_new_user_with_facebook(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess([
                'app_id' => 'test-fb-app-id',
                'user_id' => 'fb-user-1234',
            ]),
            $this->fakeFacebookMeProfile([
                'id' => 'fb-user-1234',
                'name' => 'Jane Doe',
                'email' => 'jane@facebook.com',
            ])
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'valid-fb-access-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'jane@facebook.com')
            ->assertJsonPath('data.user.name', 'Jane Doe')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.user.is_new_user', true);

        $this->assertNotNull($response->json('data.user.email_verified_at'));
        $this->assertDatabaseHas('users', ['email' => 'jane@facebook.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'facebook',
            'provider_id' => 'fb-user-1234',
            'provider_email' => 'jane@facebook.com',
        ]);
    }

    public function test_social_login_rejects_facebook_token_when_is_valid_false(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess([
                'is_valid' => false,
                'app_id' => 'test-fb-app-id',
            ]),
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'invalidated-fb-token',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_facebook_token_with_app_id_mismatch(): void
    {
        config()->set('services.facebook.app_id', 'our-real-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess([
                'is_valid' => true,
                'app_id' => 'someone-elses-fb-app-id',
            ]),
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'token-from-different-fb-app',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_expired_facebook_token(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess([
                'is_valid' => true,
                'app_id' => 'test-fb-app-id',
                'expires_at' => time() - 3600, // expired one hour ago
            ]),
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'expired-fb-token',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_accepts_facebook_token_with_zero_expires_at(): void
    {
        // expires_at: 0 means "never expires" (e.g., long-lived page tokens).
        // Must NOT be treated as "expired in 1970".
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess([
                'is_valid' => true,
                'app_id' => 'test-fb-app-id',
                'expires_at' => 0,
            ]),
            $this->fakeFacebookMeProfile([
                'id' => 'fb-long-lived-user',
                'email' => 'longlived@facebook.com',
            ])
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'never-expires-fb-token',
        ]);

        $response->assertOk()->assertJsonPath('data.user.email', 'longlived@facebook.com');
    }

    public function test_social_login_rejects_facebook_token_when_me_returns_no_email(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        // /me payload deliberately omits 'email' — simulates a user who declined
        // the email permission at the Facebook login dialog.
        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(),
            [
                'id' => '1234567890',
                'name' => 'No Email User',
                'picture' => ['data' => ['url' => 'https://scontent.example/ne.jpg']],
                // 'email' key intentionally absent
            ]
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'no-email-fb-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired token');

        // Critical: no user or social_account row should have been created.
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_social_login_rejects_facebook_token_when_me_returns_empty_email(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(),
            $this->fakeFacebookMeProfile(['email' => ''])
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'empty-email-fb-token',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_returns_existing_facebook_user_on_repeat_login(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $user = User::factory()->create(['email' => 'returning-fb@example.com']);
        $user->socialAccounts()->create([
            'provider' => 'facebook',
            'provider_id' => 'fb-returning-9999',
            'provider_email' => 'returning-fb@example.com',
        ]);

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(['user_id' => 'fb-returning-9999']),
            $this->fakeFacebookMeProfile([
                'id' => 'fb-returning-9999',
                'email' => 'returning-fb@example.com',
                'name' => 'Returning FB User',
            ])
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'valid-returning-fb-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.is_new_user', false);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_social_login_links_facebook_to_existing_email_user(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        $user = User::factory()->create([
            'email' => 'manual-signup@example.com',
            'name' => 'Manual Signup User',
        ]);

        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(['user_id' => 'fb-link-7777']),
            $this->fakeFacebookMeProfile([
                'id' => 'fb-link-7777',
                'email' => 'manual-signup@example.com',
                'name' => 'Manual Signup User',
            ])
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'valid-fb-token-matching-email',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.is_new_user', false);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_id' => 'fb-link-7777',
        ]);
    }

    public function test_social_login_rejects_when_facebook_debug_token_returns_500(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        // First call (debug_token) returns 500; second call shouldn't matter.
        $this->fakeFacebookEndpoints(
            null, // 500
            $this->fakeFacebookMeProfile()
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'any-fb-token',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_social_login_rejects_when_facebook_me_endpoint_returns_500(): void
    {
        config()->set('services.facebook.app_id', 'test-fb-app-id');
        config()->set('services.facebook.app_secret', 'test-fb-app-secret');

        // debug_token succeeds; /me returns 500.
        $this->fakeFacebookEndpoints(
            $this->fakeFacebookDebugTokenSuccess(),
            null // 500
        );

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'facebook',
            'id_token' => 'any-fb-token',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }
}
