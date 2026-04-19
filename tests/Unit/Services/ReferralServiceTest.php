<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    // ---------- createReferralCode ----------

    public function test_creates_referral_code_for_any_role(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);

        $code = $this->service->createReferralCode(
            influencer: $influencer,
            code: 'TEST2026',
            registrationBonus: 100.00,
        );

        $this->assertInstanceOf(ReferralCode::class, $code);
        $this->assertEquals('TEST2026', $code->code);
        $this->assertTrue($code->is_active);
    }

    public function test_creates_referral_code_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $code = $this->service->createReferralCode(
            influencer: $customer,
            code: 'CUST2026',
        );

        $this->assertEquals($customer->id, $code->influencer_id);
    }

    // ---------- applyReferralCode ----------

    public function test_applies_referral_code_to_vendor_application(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);
        $vendor = User::factory()->create(['role' => 'customer']);

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'code' => 'APPLY2026',
            'is_active' => true,
        ]);

        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
        ]);

        $referral = $this->service->applyReferralCode($application, 'APPLY2026');

        $this->assertInstanceOf(Referral::class, $referral);
        $this->assertEquals($influencer->id, $referral->influencer_id);
        $this->assertEquals($vendor->id, $referral->vendor_id);
    }

    public function test_apply_referral_code_rejects_self_referral(): void
    {
        $user = User::factory()->create();
        $code = ReferralCode::factory()->create([
            'influencer_id' => $user->id,
            'code' => 'SELF2026',
            'is_active' => true,
        ]);
        $application = VendorApplication::factory()->create(['user_id' => $user->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot use your own referral code.');

        $this->service->applyReferralCode($application, 'SELF2026');
    }

    // ---------- awardPoints ----------

    public function test_award_points_creates_transaction_and_increments_total(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        $transaction = $this->service->awardPoints($referral, 100);

        $this->assertInstanceOf(ReferralPointTransaction::class, $transaction);
        $this->assertEquals(100, $transaction->points);
        $this->assertEquals('vendor_onboarding', $transaction->reason);
        $this->assertEquals(100, $customer->fresh()->referral_points);
    }

    public function test_award_points_does_not_create_milestone_rewards_even_past_threshold(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        // Cross the 1000 threshold; no milestone rows should be created.
        $this->service->awardPoints($referral, 200);

        $this->assertSame(1100, (int) $customer->fresh()->referral_points);
        $this->assertDatabaseCount('referral_milestone_rewards', 0);
    }

    // ---------- activateReferral ----------

    private function createReferredApplication(User $vendor, ReferralCode $code, float $finalAmount): VendorApplication
    {
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
        ]);
        $application->forceFill([
            'referral_code_id' => $code->id,
            'onboarding_fee' => $finalAmount / 0.75, // back-compute assuming 25% subsidy
            'discount_amount' => ($finalAmount / 0.75) - $finalAmount,
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

    public function test_activate_referral_awards_points_based_on_final_amount_for_every_role(): void
    {
        Setting::set('referral_bonus_customer_pct', '10', 'number');
        Setting::set('referral_bonus_vendor_pct', '10', 'number');
        Setting::set('referral_bonus_influencer_pct', '10', 'number');
        Setting::set('referral_bonus_field_agent_pct', '10', 'number');
        Setting::set('referral_bonus_marketer_pct', '10', 'number');
        Setting::set('referral_points_per_ghs', '10', 'number');

        foreach (['customer', 'vendor', 'influencer', 'field_agent', 'marketer'] as $role) {
            $referrer = User::factory()->create(['role' => $role, 'referral_points' => 0]);
            $vendor = User::factory()->create(['role' => 'customer']);
            $code = ReferralCode::factory()->create(['influencer_id' => $referrer->id]);

            // Vendor paid 150 GHS → referrer earns 15 GHS → 150 points.
            $application = $this->createReferredApplication($vendor, $code, finalAmount: 150.0);

            $this->service->activateReferral($application);

            $this->assertSame(150, (int) $referrer->fresh()->referral_points, "role={$role}");
            $this->assertDatabaseMissing('earnings', [
                'user_id' => $referrer->id,
                'earning_type' => Earning::TYPE_REFERRAL_BONUS,
            ]);
        }
    }

    public function test_activate_referral_populates_earned_amount_on_referral(): void
    {
        Setting::set('referral_bonus_customer_pct', '10', 'number');
        Setting::set('referral_points_per_ghs', '10', 'number');

        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $customer->id]);

        $application = $this->createReferredApplication($vendor, $code, finalAmount: 150.0);

        $this->service->activateReferral($application);

        $referral = Referral::where('vendor_application_id', $application->id)->first();
        $this->assertSame('15.00', (string) $referral->earned_amount);
        $this->assertSame(Referral::STATUS_ACTIVE, $referral->status);
    }

    public function test_activate_referral_does_not_create_milestone_rewards(): void
    {
        Setting::set('referral_bonus_customer_pct', '100', 'number'); // force a large reward
        Setting::set('referral_points_per_ghs', '10', 'number');

        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $customer->id]);

        // 100% of 200 GHS × 10 = 2000 points, past the 1000 threshold.
        $application = $this->createReferredApplication($vendor, $code, finalAmount: 200.0);

        $this->service->activateReferral($application);

        $this->assertDatabaseCount('referral_milestone_rewards', 0);
    }

    public function test_activate_referral_awards_zero_points_when_role_pct_is_zero(): void
    {
        Setting::set('referral_bonus_customer_pct', '0', 'number');
        Setting::set('referral_points_per_ghs', '10', 'number');

        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $code = ReferralCode::factory()->create(['influencer_id' => $customer->id]);

        $application = $this->createReferredApplication($vendor, $code, finalAmount: 150.0);

        $referral = Referral::where('vendor_application_id', $application->id)->first();
        $this->service->activateReferral($application);

        $this->assertSame(Referral::STATUS_ACTIVE, $referral->fresh()->status);
        $this->assertSame(0, (int) $customer->fresh()->referral_points);
    }

    public function test_activate_referral_returns_null_when_application_has_no_referral_code(): void
    {
        $application = VendorApplication::factory()->create();

        $result = $this->service->activateReferral($application);

        $this->assertNull($result);
    }
}
