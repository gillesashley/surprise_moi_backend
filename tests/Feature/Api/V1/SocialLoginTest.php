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

    public function test_social_login_skips_audience_check_when_client_id_not_configured(): void
    {
        config()->set('services.google.client_id', null);

        $this->fakeGoogleTokenSuccess([
            'aud' => 'any-audience.apps.googleusercontent.com',
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'provider' => 'google',
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.is_new_user', true);
    }
}
