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
        // Test with leading zero
        $result = $this->smsService->send('0559400612', 'Test');
        $this->assertTrue($result['success']);

        // Test with international format
        $result = $this->smsService->send('233559400612', 'Test');
        $this->assertTrue($result['success']);

        // Test with plus sign
        $result = $this->smsService->send('+233559400612', 'Test');
        $this->assertTrue($result['success']);
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
