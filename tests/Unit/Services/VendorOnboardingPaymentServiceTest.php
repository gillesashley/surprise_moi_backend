<?php

namespace Tests\Unit\Services;

use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorOnboardingPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function validate_referral_code_returns_subsidy_adjusted_amounts(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');

        $vendor = User::factory()->create();
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);
        $referrer = User::factory()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $referrer->id]);

        $service = $this->app->make(VendorOnboardingPaymentService::class);
        $result = $service->validateReferralCode($code->code, $application);

        $this->assertTrue($result['valid']);
        $this->assertSame(200.0, (float) $result['onboarding_fee']);
        $this->assertSame(50.0, (float) $result['discount_amount']);
        $this->assertSame(150.0, (float) $result['final_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validate_referral_code_rejects_self_referral(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');

        $user = User::factory()->create();
        $application = VendorApplication::factory()->create([
            'user_id' => $user->id,
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);
        $code = ReferralCode::factory()->create(['influencer_id' => $user->id]);

        $service = $this->app->make(VendorOnboardingPaymentService::class);
        $result = $service->validateReferralCode($code->code, $application);

        $this->assertFalse($result['valid']);
        $this->assertSame('You cannot use your own referral code.', $result['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validate_referral_code_rejects_invalid_code(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');

        $application = VendorApplication::factory()->create([
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);
        $service = $this->app->make(VendorOnboardingPaymentService::class);

        $result = $service->validateReferralCode('BOGUS-CODE', $application);

        $this->assertFalse($result['valid']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function initialize_payment_persists_referral_code_id_and_final_amount(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');

        $vendor = User::factory()->create();
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'business_certificate_document' => 'certificates/doc.pdf',
            'completed_step' => 4,
            'payment_required' => true,
        ]);
        $referrer = User::factory()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $referrer->id]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://paystack.test/authz',
                    'access_code' => 'AC_TEST_123',
                    'reference' => 'VOP-FAKEREF',
                ],
            ], 200),
        ]);

        $service = $this->app->make(VendorOnboardingPaymentService::class);
        $result = $service->initializePayment($application, $code->code);

        $this->assertTrue($result['success']);
        $this->assertSame(150.0, (float) $result['payment']->amount);
        $this->assertSame(50.0, (float) $result['payment']->discount_amount);
        $this->assertSame($code->id, $result['payment']->referral_code_id);
        $this->assertSame(150.0, (float) $application->fresh()->final_amount);
        $this->assertSame($code->id, $application->fresh()->referral_code_id);
    }
}
