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

class DynamicRegistrationBonusTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    // -------------------------------------------------------------------------
    // calculateRegistrationBonus
    // -------------------------------------------------------------------------

    public function test_calculate_registration_bonus_for_influencer_tier_1(): void
    {
        Setting::set('referral_bonus_influencer_pct', 25, 'number');
        Setting::set('vendor_tier1_onboarding_fee', 150, 'number');

        $bonus = $this->service->calculateRegistrationBonus('influencer', 1);

        $this->assertSame(37.50, $bonus);
    }

    public function test_calculate_registration_bonus_for_vendor_tier_2(): void
    {
        Setting::set('referral_bonus_vendor_pct', 20, 'number');
        Setting::set('vendor_tier2_onboarding_fee', 100, 'number');

        $bonus = $this->service->calculateRegistrationBonus('vendor', 2);

        $this->assertSame(20.00, $bonus);
    }

    public function test_calculate_registration_bonus_returns_zero_for_unmapped_role(): void
    {
        // admin and super_admin are not mapped in the referral bonus settings
        $bonus = $this->service->calculateRegistrationBonus('admin', 1);

        $this->assertSame(0.0, $bonus);
    }

    public function test_calculate_registration_bonus_returns_zero_when_percentage_is_zero(): void
    {
        Setting::set('referral_bonus_employee_pct', 0, 'number');
        Setting::set('vendor_tier1_onboarding_fee', 200, 'number');

        $bonus = $this->service->calculateRegistrationBonus('employee', 1);

        $this->assertSame(0.0, $bonus);
    }

    // -------------------------------------------------------------------------
    // activateReferral — dynamic bonus path (registration_bonus = 0 on code)
    // -------------------------------------------------------------------------

    public function test_activate_referral_uses_dynamic_bonus_when_code_has_no_stored_bonus(): void
    {
        // Migration seeds 25% for influencer and 150 for tier1, giving 37.50
        $influencer = User::factory()->influencer()->create();
        $vendorUser = User::factory()->create(['role' => 'customer']);

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'registration_bonus' => 0, // new code — no stored bonus
        ]);

        // Use forceFill to bypass $fillable guard for referral_code_id
        $vendorApplication = VendorApplication::factory()
            ->make([
                'user_id' => $vendorUser->id,
                'has_business_certificate' => true, // tier 1
            ]);
        $vendorApplication->forceFill(['referral_code_id' => $referralCode->id])->save();

        Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'vendor_id' => $vendorUser->id,
            'vendor_application_id' => $vendorApplication->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($vendorApplication);

        $earning = Earning::where('user_id', $influencer->id)
            ->where('earning_type', Earning::TYPE_REFERRAL_BONUS)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(37.50, $earning->amount);
        $this->assertSame(Earning::STATUS_PENDING, $earning->status);
    }

    // -------------------------------------------------------------------------
    // activateReferral — legacy fallback path (registration_bonus > 0 on code)
    // -------------------------------------------------------------------------

    public function test_activate_referral_uses_stored_bonus_for_legacy_codes(): void
    {
        // Even if a dynamic setting exists, the stored bonus takes precedence.
        // Migration seeds 25% for influencer and 150 for tier1 (= 37.50 dynamic),
        // but we assert 75.00 is used because registration_bonus > 0 on the code.
        $influencer = User::factory()->influencer()->create();
        $vendorUser = User::factory()->create(['role' => 'customer']);

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'registration_bonus' => 75.00, // legacy stored bonus
        ]);

        // Use forceFill to bypass $fillable guard for referral_code_id
        $vendorApplication = VendorApplication::factory()
            ->make([
                'user_id' => $vendorUser->id,
                'has_business_certificate' => true,
            ]);
        $vendorApplication->forceFill(['referral_code_id' => $referralCode->id])->save();

        Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'vendor_id' => $vendorUser->id,
            'vendor_application_id' => $vendorApplication->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($vendorApplication);

        $earning = Earning::where('user_id', $influencer->id)
            ->where('earning_type', Earning::TYPE_REFERRAL_BONUS)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(75.00, $earning->amount);
        $this->assertSame(Earning::STATUS_PENDING, $earning->status);
    }
}
