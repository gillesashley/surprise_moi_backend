<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralBonusSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_includes_referral_bonus_percentages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Setting::set('referral_bonus_customer_pct', 15.00, 'number');

        $response = $this->actingAs($admin)->get('/settings/vendor-onboarding');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/vendor-onboarding')
            ->has('settings.referral_bonus_customer_pct')
        );
    }

    public function test_update_saves_referral_bonus_percentages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        Setting::set('vendor_tier1_onboarding_fee', 150.00, 'number');
        Setting::set('vendor_tier2_onboarding_fee', 100.00, 'number');
        Setting::set('vendor_tier1_commission_rate', 12.00, 'number');
        Setting::set('vendor_tier2_commission_rate', 8.00, 'number');

        $response = $this->actingAs($admin)->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '150.00',
            'vendor_tier2_onboarding_fee' => '100.00',
            'vendor_tier1_commission_rate' => '12.00',
            'vendor_tier2_commission_rate' => '8.00',
            'referral_bonus_customer_pct' => '18.00',
            'referral_bonus_vendor_pct' => '22.00',
            'referral_bonus_influencer_pct' => '28.00',
            'referral_bonus_field_agent_pct' => '35.00',
            'referral_bonus_employee_pct' => '25.00',
            'vendor_onboarding_subsidy_pct' => '25.00',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
        ]);

        $response->assertRedirect();
        $this->assertEquals(18.00, Setting::get('referral_bonus_customer_pct'));
        $this->assertEquals(22.00, Setting::get('referral_bonus_vendor_pct'));
        $this->assertEquals(28.00, Setting::get('referral_bonus_influencer_pct'));
        $this->assertEquals(35.00, Setting::get('referral_bonus_field_agent_pct'));
        $this->assertEquals(25.00, Setting::get('referral_bonus_employee_pct'));
    }

    public function test_update_rejects_percentage_over100(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '150.00',
            'vendor_tier2_onboarding_fee' => '100.00',
            'vendor_tier1_commission_rate' => '12.00',
            'vendor_tier2_commission_rate' => '8.00',
            'referral_bonus_customer_pct' => '150.00',
            'referral_bonus_vendor_pct' => '20.00',
            'referral_bonus_influencer_pct' => '25.00',
            'referral_bonus_field_agent_pct' => '30.00',
            'referral_bonus_employee_pct' => '20.00',
            'vendor_onboarding_subsidy_pct' => '25.00',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
        ]);

        $response->assertSessionHasErrors('referral_bonus_customer_pct');
    }
}
