<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use App\Notifications\VendorOnboardingPaidNotification;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOnboardingPaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_when_vendor_completes_onboarding_payment(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $vendor = User::factory()->create(['role' => 'customer']);
        $application = VendorApplication::factory()
            ->for($vendor)
            ->registeredVendor()
            ->pending()
            ->create(['onboarding_fee' => 100.00]);

        $payment = VendorOnboardingPayment::factory()->create([
            'user_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 10000,
                    'currency' => 'GHS',
                    'reference' => $payment->reference,
                    'gateway_response' => 'Successful',
                    'channel' => 'card',
                    'paid_at' => now()->toIso8601String(),
                    'authorization' => [
                        'last4' => '4081',
                        'card_type' => 'visa',
                        'bank' => 'TEST BANK',
                        'exp_month' => '12',
                        'exp_year' => '2030',
                    ],
                    'metadata' => [],
                ],
            ]),
        ]);

        $service = app(VendorOnboardingPaymentService::class);
        $result = $service->verifyPayment($payment);

        $this->assertTrue($result['success']);

        Notification::assertSentTo($admin, VendorOnboardingPaidNotification::class);
        Notification::assertSentTo($superAdmin, VendorOnboardingPaidNotification::class);
        Notification::assertNotSentTo($customer, VendorOnboardingPaidNotification::class);
    }

    public function test_notification_contains_correct_vendor_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        $vendor = User::factory()->create(['name' => 'Test Vendor']);
        $application = VendorApplication::factory()
            ->for($vendor)
            ->registeredVendor()
            ->create(['onboarding_fee' => 150.00]);

        $notification = new VendorOnboardingPaidNotification($application);

        $mailData = $notification->toMail($admin);
        $this->assertSame('New Vendor Onboarding Payment Received', $mailData->subject);
        $this->assertStringContainsString('Test Vendor', implode(' ', $mailData->introLines));
        $this->assertStringContainsString('Tier 1 (Business)', implode(' ', $mailData->introLines));

        $dbData = $notification->toDatabase($admin);
        $this->assertSame('vendor_onboarding_paid', $dbData['type']);
        $this->assertStringContainsString('Test Vendor', $dbData['message']);
        $this->assertSame($vendor->id, $dbData['actor']['id']);
    }

    public function test_no_notification_sent_when_payment_fails(): void
    {
        Notification::fake();

        User::factory()->create(['role' => 'admin']);

        $vendor = User::factory()->create(['role' => 'customer']);
        $application = VendorApplication::factory()
            ->for($vendor)
            ->pending()
            ->create(['onboarding_fee' => 100.00]);

        $payment = VendorOnboardingPayment::factory()->create([
            'user_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'failed',
                    'amount' => 10000,
                    'currency' => 'GHS',
                    'reference' => $payment->reference,
                    'gateway_response' => 'Declined',
                    'channel' => 'card',
                    'metadata' => [],
                ],
            ]),
        ]);

        $service = app(VendorOnboardingPaymentService::class);
        $result = $service->verifyPayment($payment);

        $this->assertFalse($result['success']);
        Notification::assertNothingSent();
    }
}
