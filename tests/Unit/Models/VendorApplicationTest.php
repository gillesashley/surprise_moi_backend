<?php

namespace Tests\Unit\Models;

use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApplicationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function calculate_final_amount_without_referral_code_returns_full_fee(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        $application = VendorApplication::factory()->create([
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);

        $amounts = $application->calculateFinalAmount(null);

        $this->assertSame(200.0, $amounts['onboarding_fee']);
        $this->assertSame(0.0, $amounts['discount_amount']);
        $this->assertSame(200.0, $amounts['final_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function calculate_final_amount_with_referral_code_applies_subsidy(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');
        $application = VendorApplication::factory()->create([
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);
        $code = ReferralCode::factory()->create();

        $amounts = $application->calculateFinalAmount($code);

        $this->assertSame(200.0, $amounts['onboarding_fee']);
        $this->assertSame(50.0, $amounts['discount_amount']);
        $this->assertSame(150.0, $amounts['final_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function calculate_final_amount_ignores_invalid_referral_code(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');
        $application = VendorApplication::factory()->create([
            'business_certificate_document' => 'certificates/doc.pdf',
        ]);
        $code = ReferralCode::factory()->create(['is_active' => false]);

        $amounts = $application->calculateFinalAmount($code);

        $this->assertSame(0.0, $amounts['discount_amount']);
        $this->assertSame(200.0, $amounts['final_amount']);
    }
}
