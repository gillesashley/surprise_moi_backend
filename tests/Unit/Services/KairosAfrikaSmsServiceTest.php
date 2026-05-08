<?php

namespace Tests\Unit\Services;

use App\Contracts\Sms\SmsProviderInterface;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KairosAfrikaSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected KairosAfrikaSmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure service for testing
        Config::set('services.kairosafrika.api_url', 'https://api.test.com');
        Config::set('services.kairosafrika.api_key', 'test_key');
        Config::set('services.kairosafrika.api_secret', 'test_secret');
        Config::set('services.kairosafrika.api_version', 'v1');
        Config::set('services.kairosafrika.sender_name', 'TestSender');
        Config::set('services.kairosafrika.log_only', true);

        $this->smsService = new KairosAfrikaSmsService;
    }

    /**
     * Test that service implements SmsProviderInterface.
     */
    public function test_service_implements_interface(): void
    {
        $this->assertInstanceOf(SmsProviderInterface::class, $this->smsService);
    }

    /**
     * Test backward compatibility - sendOtp method still works.
     */
    public function test_send_otp_returns_expected_structure(): void
    {
        $result = $this->smsService->sendOtp('0559400612');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test backward compatibility - validateOtp method still works.
     */
    public function test_validate_otp_returns_expected_structure(): void
    {
        $result = $this->smsService->validateOtp('1234', '0559400612');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
    }

    /**
     * Test new send method for notification channel support.
     */
    public function test_send_method_returns_expected_structure(): void
    {
        $result = $this->smsService->send('0559400612', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test phone number formatting (0559400612 -> 233559400612).
     */
    public function test_phone_number_formatting(): void
    {
        // Ghana local with trunk prefix
        $result = $this->smsService->send('0559400612', 'Test');
        $this->assertTrue($result['success']);

        // Canonical E.164
        $result = $this->smsService->send('+233559400612', 'Test');
        $this->assertTrue($result['success']);
    }

    /**
     * The formatter must produce the digits-only international form Kairos
     * expects, regardless of which acceptable input shape it receives.
     */
    public function test_format_phone_number_normalizes_to_digits_international(): void
    {
        $reflection = new \ReflectionMethod($this->smsService, 'formatPhoneNumber');
        $reflection->setAccessible(true);

        $cases = [
            '0559400612' => '233559400612',           // Ghana local, trunk-prefixed
            '+233559400612' => '233559400612',        // canonical E.164
            ' +233 559 400 612 ' => '233559400612',   // whitespace tolerated by libphonenumber
            '+12025550123' => '12025550123',          // foreign country (US) preserved
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame(
                $expected,
                $reflection->invoke($this->smsService, $input),
                "formatPhoneNumber({$input}) should return {$expected}",
            );
        }
    }

    /**
     * Bad input is the FormRequest's job to reject — but if the service is
     * ever called with garbage anyway, it must fail loudly rather than silently
     * dispatch to a non-existent number.
     */
    public function test_format_phone_number_throws_on_unparseable_input(): void
    {
        $reflection = new \ReflectionMethod($this->smsService, 'formatPhoneNumber');
        $reflection->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $reflection->invoke($this->smsService, 'not-a-phone');
    }

    /**
     * Test interface binding works via dependency injection.
     */
    public function test_interface_binding_works(): void
    {
        $resolved = app(SmsProviderInterface::class);

        $this->assertInstanceOf(KairosAfrikaSmsService::class, $resolved);
    }
}
