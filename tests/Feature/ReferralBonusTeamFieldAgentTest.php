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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReferralBonusTeamFieldAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeReferralFor(User $sharer): Referral
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);

        $code = ReferralCode::create([
            'influencer_id' => $sharer->id,
            'is_active' => true,
            'prefix' => ReferralCode::getPrefixForRole($sharer->role),
        ]);

        $vendorApp = VendorApplication::factory()->make([
            'user_id' => $vendor->id,
            'has_business_certificate' => true,
        ]);
        $vendorApp->forceFill([
            'status' => VendorApplication::STATUS_APPROVED,
            'referral_code_id' => $code->id,
            'referral_code_used' => $code->code,
            'final_amount' => 200.00,
        ])->save();

        return Referral::factory()->create([
            'referral_code_id' => $code->id,
            'influencer_id' => $sharer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $vendorApp->id,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function test_individual_field_agent_uses_individual_setting_key(): void
    {
        Cache::flush();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Setting::set('referral_bonus_field_agent_team_pct', '50', 'number');

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => false,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // 30% of 200 = 60 GHS
        $this->assertEqualsWithDelta(60.00, (float) $referral->fresh()->earned_amount, 0.01);

        $earning = Earning::where('user_id', $agent->id)->firstOrFail();
        $this->assertEqualsWithDelta(60.00, (float) $earning->amount, 0.01);
    }

    public function test_team_field_agent_uses_team_setting_key(): void
    {
        Cache::flush();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Setting::set('referral_bonus_field_agent_team_pct', '50', 'number');

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // 50% of 200 = 100 GHS
        $this->assertEqualsWithDelta(100.00, (float) $referral->fresh()->earned_amount, 0.01);

        $earning = Earning::where('user_id', $agent->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.00, (float) $earning->amount, 0.01);
    }

    public function test_team_field_agent_falls_back_to_zero_when_team_setting_missing(): void
    {
        Cache::flush();
        Setting::where('key', 'referral_bonus_field_agent_team_pct')->delete();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Cache::flush();

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // Falls back to 0; no Earning row should exist (creation gated on amount > 0)
        $this->assertEqualsWithDelta(0.00, (float) $referral->fresh()->earned_amount, 0.01);
        $this->assertSame(0, Earning::where('user_id', $agent->id)->count());
    }
}
