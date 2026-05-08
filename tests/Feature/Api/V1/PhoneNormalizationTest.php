<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies that the phone-normalization layer added to all Auth FormRequests
 * (NormalizesPhone trait + Phone validation rule) coerces every accepted
 * input shape to canonical E.164 before reaching the controller / DB / SMS
 * service, and rejects unparseable input with a 422.
 */
class PhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function mockSmsServiceExpecting(string $expectedE164): void
    {
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) use ($expectedE164) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->with($expectedE164)
                ->andReturn([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => ['transactionId' => 'test-tx-id'],
                ]);
        });
    }

    public function test_send_otp_normalizes_ghana_local_to_e164(): void
    {
        $this->mockSmsServiceExpecting('+233559400612');

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0559400612',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.transaction_id', 'test-tx-id');
    }

    public function test_send_otp_normalizes_canonical_e164_unchanged(): void
    {
        $this->mockSmsServiceExpecting('+233559400612');

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+233559400612',
        ]);

        $response->assertStatus(200);
    }

    public function test_send_otp_strips_whitespace_and_punctuation(): void
    {
        $this->mockSmsServiceExpecting('+233559400612');

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+233 (55) 940-0612',
        ]);

        $response->assertStatus(200);
    }

    public function test_send_otp_rejects_unparseable_input_with_422(): void
    {
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => 'not-a-phone',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_otp_rejects_too_short_number_with_422(): void
    {
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+233123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_stores_canonical_e164_regardless_of_input_format(): void
    {
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->with('+233559400612')
                ->andReturn([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => ['transactionId' => 'test-tx-id'],
                ]);
        });

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '0559400612',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.transaction_id', 'test-tx-id');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'phone' => '+233559400612',
        ]);
    }

    public function test_resend_otp_finds_user_when_input_format_differs_from_stored(): void
    {
        // User row stored canonical (post-migration state).
        User::factory()->phoneUnverified()->create(['phone' => '+233559400612']);

        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->with('+233559400612')
                ->andReturn([
                    'success' => true,
                    'message' => 'OTP sent',
                    'data' => ['transactionId' => 'tx-2'],
                ]);
        });

        // Client sends Ghana-local form; lookup must still find the canonical row.
        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '0559400612',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction_id', 'tx-2');
    }

    public function test_register_rejects_landline_number(): void
    {
        $this->mock(KairosAfrikaSmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        // US landline: NPA 202 (Washington DC) area code, but specific number range
        // libphonenumber classifies as fixed-line.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Land Line',
            'email' => 'fixed@example.com',
            'phone' => '+12022345678',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}
