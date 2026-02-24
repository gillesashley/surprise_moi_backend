<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
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
}
