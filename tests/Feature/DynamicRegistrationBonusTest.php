<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * After the 2026-04 referral redesign, activation awards points (not GHS
 * Earnings) to every role. The reward base is what the vendor actually paid
 * (post-subsidy), not the tier fee. This test verifies that shape.
 */
class DynamicRegistrationBonusTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    private function seedPointsConversion(int $pointsPerGhs = 10): void
    {
        Setting::set('referral_points_per_ghs', (string) $pointsPerGhs, 'number');
    }

    private function makeReferredApplication(
        User $vendor,
        ReferralCode $code,
        float $finalAmount,
        float $onboardingFee,
    ): VendorApplication {
        $application = VendorApplication::factory()
            ->make([
                'user_id' => $vendor->id,
                'has_business_certificate' => true,
            ]);
        $application->forceFill([
            'referral_code_id' => $code->id,
            'onboarding_fee' => $onboardingFee,
            'discount_amount' => $onboardingFee - $finalAmount,
            'final_amount' => $finalAmount,
        ])->save();

        Referral::factory()->create([
            'referral_code_id' => $code->id,
            'influencer_id' => $code->influencer_id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        return $application;
    }

    public function test_activate_referral_awards_points_for_influencer(): void
    {
        $this->seedPointsConversion();
        Setting::set('referral_bonus_influencer_pct', '25', 'number');

        $influencer = User::factory()->influencer()->create(['referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $influencer->id]);

        // Vendor paid 150 after 25% subsidy on 200.
        $application = $this->makeReferredApplication($vendor, $code, finalAmount: 150.0, onboardingFee: 200.0);

        $this->service->activateReferral($application);

        $this->assertSame(375, (int) $influencer->fresh()->referral_points); // 150 * 0.25 * 10
        $this->assertDatabaseMissing('earnings', [
            'user_id' => $influencer->id,
            'earning_type' => Earning::TYPE_REFERRAL_BONUS,
        ]);
    }

    public function test_activate_referral_awards_points_for_non_earning_role(): void
    {
        $this->seedPointsConversion();
        Setting::set('referral_bonus_customer_pct', '10', 'number');

        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $customer->id]);

        $application = $this->makeReferredApplication($vendor, $code, finalAmount: 150.0, onboardingFee: 200.0);

        $this->service->activateReferral($application);

        $this->assertSame(150, (int) $customer->fresh()->referral_points); // 150 * 0.10 * 10
        $this->assertDatabaseMissing('earnings', [
            'user_id' => $customer->id,
            'earning_type' => Earning::TYPE_REFERRAL_BONUS,
        ]);
    }

    public function test_activate_referral_awards_no_points_when_role_percentage_is_zero(): void
    {
        $this->seedPointsConversion();
        Setting::set('referral_bonus_customer_pct', '0', 'number');

        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $customer->id]);

        $application = $this->makeReferredApplication($vendor, $code, finalAmount: 150.0, onboardingFee: 200.0);

        $this->service->activateReferral($application);

        $this->assertSame(0, (int) $customer->fresh()->referral_points);
    }

    public function test_activate_referral_populates_earned_amount_on_referral_row(): void
    {
        $this->seedPointsConversion();
        Setting::set('referral_bonus_influencer_pct', '25', 'number');

        $influencer = User::factory()->influencer()->create();
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $influencer->id]);

        $application = $this->makeReferredApplication($vendor, $code, finalAmount: 150.0, onboardingFee: 200.0);

        $this->service->activateReferral($application);

        $referral = Referral::where('vendor_application_id', $application->id)->first();
        $this->assertSame('37.50', (string) $referral->earned_amount);
    }
}
