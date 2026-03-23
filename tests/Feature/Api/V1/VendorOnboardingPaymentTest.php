<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOnboardingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createSubmittableApplication(User $user): VendorApplication
    {
        return VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'user_id' => $user->id,
                'payment_required' => true,
                'payment_completed' => false,
            ]);
    }

    public function test_application_is_auto_submitted_after_successful_payment_verification(): void
    {
        Notification::fake();
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => 'VOP-TESTREF123',
                    'amount' => 10000,
                    'currency' => 'GHS',
                    'channel' => 'mobile_money',
                    'paid_at' => '2026-03-21T09:05:00.000Z',
                    'gateway_response' => 'Successful',
                    'authorization' => [
                        'last4' => 'X236',
                        'bank' => 'VODAFONE',
                        'channel' => 'mobile_money',
                        'mobile_money_number' => '0500949236',
                    ],
                    'log' => null,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $application = $this->createSubmittableApplication($user);
        $payment = VendorOnboardingPayment::factory()->create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
            'reference' => 'VOP-TESTREF123',
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        $service = app(VendorOnboardingPaymentService::class);
        $result = $service->verifyPayment($payment);

        $this->assertTrue($result['success']);

        $application->refresh();
        $this->assertTrue($application->payment_completed);
        $this->assertNotNull($application->submitted_at, 'Application should be auto-submitted after payment');
    }

    public function test_already_successful_payment_triggers_auto_submit_if_not_submitted(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $application = $this->createSubmittableApplication($user);
        // Simulate: payment succeeded but application wasn't submitted
        $application->update([
            'payment_completed' => true,
            'payment_completed_at' => now(),
        ]);
        $payment = VendorOnboardingPayment::factory()->successful()->create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
        ]);

        $this->assertNull($application->fresh()->submitted_at, 'Application should NOT be submitted yet');

        $service = app(VendorOnboardingPaymentService::class);
        $result = $service->verifyPayment($payment);

        $this->assertTrue($result['success']);
        $this->assertNotNull($application->fresh()->submitted_at, 'Application should be auto-submitted on re-verify');
    }

    public function test_auto_submit_does_not_double_submit(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $application = $this->createSubmittableApplication($user);
        $submittedAt = now()->subMinutes(5);
        $application->update([
            'payment_completed' => true,
            'payment_completed_at' => now(),
            'submitted_at' => $submittedAt,
        ]);
        $payment = VendorOnboardingPayment::factory()->successful()->create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
        ]);

        $service = app(VendorOnboardingPaymentService::class);
        $result = $service->verifyPayment($payment);

        $this->assertTrue($result['success']);
        // submitted_at should NOT change since canSubmit() returns false when already submitted
        $this->assertEquals(
            $submittedAt->toDateTimeString(),
            $application->fresh()->submitted_at->toDateTimeString(),
            'submitted_at should not change on already-submitted application'
        );
    }
}
