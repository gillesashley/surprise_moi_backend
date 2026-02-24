<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        // Mock the SMS service to avoid actual API calls
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => ['transactionId' => 'test-123'],
                ]);
        });

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0559400612',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role', 'phone_verified_at'],
                    'token',
                    'token_type',
                    'otp_sent',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'otp_sent' => true,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'phone' => '0559400612',
        ]);
    }

    public function test_registration_requires_phone_number(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'phone' => '0559400612',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '0559400612']);

        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0559400612',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_verify_phone_with_valid_otp(): void
    {
        $user = User::factory()->phoneUnverified()->create([
            'phone' => '0559400612',
        ]);

        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateOtp')
                ->once()
                ->with('1234', '0559400612')
                ->andReturn([
                    'success' => true,
                    'message' => 'Verification successful',
                    'data' => ['transactionId' => 'test-123'],
                ]);
        });

        $response = $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '0559400612',
            'code' => '1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Phone number verified successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'phone_verified_at'],
                ],
            ]);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_verification_fails_with_invalid_otp(): void
    {
        $user = User::factory()->phoneUnverified()->create([
            'phone' => '0559400612',
        ]);

        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateOtp')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Invalid or expired OTP',
                    'data' => null,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '0559400612',
            'code' => '9999',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ]);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_verification_fails_when_already_verified(): void
    {
        User::factory()->create([
            'phone' => '0559400612',
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '0559400612',
            'code' => '1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Phone number already verified',
            ]);
    }

    public function test_user_can_resend_otp(): void
    {
        User::factory()->phoneUnverified()->create([
            'phone' => '0559400612',
        ]);

        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->with('0559400612')
                ->andReturn([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => ['transactionId' => 'test-123'],
                ]);
        });

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '0559400612',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent successfully',
            ]);
    }

    public function test_resend_otp_fails_when_phone_already_verified(): void
    {
        User::factory()->create([
            'phone' => '0559400612',
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '0559400612',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Phone number already verified',
            ]);
    }

    public function test_resend_otp_fails_for_nonexistent_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '0559999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'phone_verified_at'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        $this->assertCount(0, $user->tokens);
    }

    public function test_user_can_get_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('token-1');
        $user->createToken('token-2');

        $response = $this->actingAs($user)->getJson('/api/v1/auth/tokens');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.tokens');
    }
}
