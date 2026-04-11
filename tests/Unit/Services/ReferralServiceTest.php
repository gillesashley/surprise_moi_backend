<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralMilestoneReward;
use App\Models\ReferralPointTransaction;
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
        $this->service = new ReferralService;
    }

    public function test_creates_referral_code_for_influencer(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);

        $code = $this->service->createReferralCode(
            influencer: $influencer,
            code: 'TEST2024',
            registrationBonus: 100.00,
            commissionRate: 10.00
        );

        $this->assertInstanceOf(ReferralCode::class, $code);
        $this->assertEquals('TEST2024', $code->code);
        $this->assertEquals(100.00, $code->registration_bonus);
        $this->assertTrue($code->is_active);
    }

    public function test_creates_referral_code_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $code = $this->service->createReferralCode(
            influencer: $customer,
            code: 'CUST2026',
            registrationBonus: 50.00,
        );

        $this->assertInstanceOf(ReferralCode::class, $code);
        $this->assertEquals('CUST2026', $code->code);
        $this->assertEquals($customer->id, $code->influencer_id);
    }

    public function test_creates_referral_code_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $code = $this->service->createReferralCode(
            influencer: $admin,
            code: 'ADM2026',
        );

        $this->assertInstanceOf(ReferralCode::class, $code);
        $this->assertEquals($admin->id, $code->influencer_id);
    }

    public function test_applies_referral_code_to_vendor_application(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);
        $vendor = User::factory()->create(['role' => 'customer']);

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'code' => 'APPLY2024',
            'is_active' => true,
        ]);

        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
        ]);

        $referral = $this->service->applyReferralCode($application, 'APPLY2024');

        $this->assertInstanceOf(Referral::class, $referral);
        $this->assertEquals($influencer->id, $referral->influencer_id);
        $this->assertEquals($vendor->id, $referral->vendor_id);
    }

    public function test_calculates_commission_from_order(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'commission_rate' => 5.00,
        ]);

        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'status' => Referral::STATUS_ACTIVE,
            'commission_expires_at' => now()->addMonths(2),
        ]);

        $earning = $this->service->calculateCommission($referral, 1000.00);

        $this->assertInstanceOf(Earning::class, $earning);
        $this->assertEquals(50.00, $earning->amount);
    }

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

    public function test_award_points_triggers_milestone_at_1000(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        $this->service->awardPoints($referral, 100);

        $milestone = ReferralMilestoneReward::where('user_id', $customer->id)
            ->where('threshold', 1000)
            ->first();

        $this->assertNotNull($milestone);
        $this->assertEquals(ReferralMilestoneReward::STATUS_PENDING, $milestone->status);
        $this->assertEquals(1000, $milestone->points_at_milestone);
    }

    public function test_award_points_triggers_milestone_at_5000(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 4900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        $this->service->awardPoints($referral, 100);

        $milestone = ReferralMilestoneReward::where('user_id', $customer->id)
            ->where('threshold', 5000)
            ->first();

        $this->assertNotNull($milestone);
    }

    public function test_award_points_triggers_milestone_at_10000_after_5000(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 9900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        $this->service->awardPoints($referral, 100);

        $this->assertDatabaseHas('referral_milestone_rewards', [
            'user_id' => $customer->id,
            'threshold' => 10000,
        ]);
    }

    public function test_crossing_multiple_milestones_in_one_award_creates_all_rows(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        // Jump from 900 to 5100 in a single award
        $this->service->awardPoints($referral, 4200);

        $this->assertDatabaseHas('referral_milestone_rewards', [
            'user_id' => $customer->id,
            'threshold' => 1000,
        ]);
        $this->assertDatabaseHas('referral_milestone_rewards', [
            'user_id' => $customer->id,
            'threshold' => 5000,
        ]);
        $this->assertEquals(2, ReferralMilestoneReward::where('user_id', $customer->id)->count());
    }

    public function test_award_points_is_idempotent_for_same_threshold(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 900]);
        $referralCode = ReferralCode::factory()->create(['influencer_id' => $customer->id]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
        ]);

        // First award crosses 1000
        $this->service->awardPoints($referral, 100);
        // Second award stays above 1000 but does NOT re-cross
        $this->service->awardPoints($referral, 100);

        $this->assertEquals(
            1,
            ReferralMilestoneReward::where('user_id', $customer->id)
                ->where('threshold', 1000)
                ->count()
        );
    }

    public function test_activate_referral_creates_earning_for_influencer(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer', 'name' => 'Jane Vendor']);
        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'registration_bonus' => 75.00,
        ]);
        $application = \App\Models\VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
        ]);
        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($application);

        $this->assertDatabaseHas('earnings', [
            'user_id' => $influencer->id,
            'user_role' => 'influencer',
            'earning_type' => Earning::TYPE_REFERRAL_BONUS,
            'amount' => 75.00,
        ]);
        $this->assertEquals(0, $influencer->fresh()->referral_points);
        $this->assertDatabaseMissing('referral_point_transactions', [
            'user_id' => $influencer->id,
        ]);
    }

    public function test_activate_referral_creates_earning_for_field_agent_with_correct_user_role(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);
        $vendor = User::factory()->create(['role' => 'customer']);
        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $fieldAgent->id,
            'registration_bonus' => 50.00,
        ]);
        $application = \App\Models\VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
        ]);
        Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $fieldAgent->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($application);

        $this->assertDatabaseHas('earnings', [
            'user_id' => $fieldAgent->id,
            'user_role' => 'field_agent', // was hardcoded 'influencer' — latent bug now fixed
        ]);
    }

    public function test_activate_referral_awards_points_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
        $vendor = User::factory()->create(['role' => 'customer']);
        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $customer->id,
            'registration_bonus' => 50.00,
        ]);
        $application = \App\Models\VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
        ]);
        Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($application);

        $this->assertEquals(100, $customer->fresh()->referral_points);
        $this->assertDatabaseHas('referral_point_transactions', [
            'user_id' => $customer->id,
            'points' => 100,
            'reason' => 'vendor_onboarding',
        ]);
        $this->assertDatabaseMissing('earnings', [
            'user_id' => $customer->id,
        ]);
    }

    public function test_activate_referral_awards_points_for_admin_without_creating_earning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->create(['role' => 'customer']);
        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $admin->id,
            'registration_bonus' => 50.00,
        ]);
        $application = \App\Models\VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
        ]);
        Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $admin->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($application);

        $this->assertEquals(100, $admin->fresh()->referral_points);
        $this->assertDatabaseMissing('earnings', ['user_id' => $admin->id]);
    }
}
