<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->mockConsoleOutput = false;
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_access_vendor_onboarding_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get('/settings/vendor-onboarding');

        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_vendor_onboarding_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get('/settings/vendor-onboarding');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_vendor_onboarding_settings(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->get('/settings/vendor-onboarding');

        // Dashboard middleware redirects non-dashboard users
        $response->assertRedirect();
    }

    public function test_super_admin_can_update_vendor_onboarding_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => 100,
                'vendor_tier2_onboarding_fee' => 200,
                'vendor_tier1_commission_rate' => 10,
                'vendor_tier2_commission_rate' => 15,
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'referral_bonus_field_agent_team_pct' => '35',
                'vendor_onboarding_subsidy_pct' => '25',
                'referral_points_per_ghs' => '10',
                'referral_cashout_min_points' => '1000',
            ]);

        $response->assertRedirect();
    }

    public function test_admin_cannot_update_vendor_onboarding_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => 100,
                'vendor_tier2_onboarding_fee' => 200,
                'vendor_tier1_commission_rate' => 10,
                'vendor_tier2_commission_rate' => 15,
            ]);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function migration_seeds_the_three_new_referral_program_settings(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertSame('25.00', \App\Models\Setting::get('vendor_onboarding_subsidy_pct'));
        $this->assertSame('10', \App\Models\Setting::get('referral_points_per_ghs'));
        $this->assertSame('1000', \App\Models\Setting::get('referral_cashout_min_points'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_update_the_three_new_referral_program_settings(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => '200',
                'vendor_tier2_onboarding_fee' => '100',
                'vendor_tier1_commission_rate' => '12',
                'vendor_tier2_commission_rate' => '8',
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'referral_bonus_field_agent_team_pct' => '35',
                'vendor_onboarding_subsidy_pct' => '30',
                'referral_points_per_ghs' => '15',
                'referral_cashout_min_points' => '2000',
            ])
            ->assertRedirect();

        $this->assertEquals(30.0, (float) \App\Models\Setting::get('vendor_onboarding_subsidy_pct'));
        $this->assertEquals(15.0, (float) \App\Models\Setting::get('referral_points_per_ghs'));
        $this->assertEquals(2000.0, (float) \App\Models\Setting::get('referral_cashout_min_points'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function subsidy_pct_must_be_between_0_and_100(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => '200',
                'vendor_tier2_onboarding_fee' => '100',
                'vendor_tier1_commission_rate' => '12',
                'vendor_tier2_commission_rate' => '8',
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'referral_bonus_field_agent_team_pct' => '35',
                'vendor_onboarding_subsidy_pct' => '150',
                'referral_points_per_ghs' => '10',
                'referral_cashout_min_points' => '1000',
            ])
            ->assertSessionHasErrors('vendor_onboarding_subsidy_pct');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function team_field_agent_pct_is_required_on_update(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => '200',
                'vendor_tier2_onboarding_fee' => '100',
                'vendor_tier1_commission_rate' => '12',
                'vendor_tier2_commission_rate' => '8',
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'vendor_onboarding_subsidy_pct' => '25',
                'referral_points_per_ghs' => '10',
                'referral_cashout_min_points' => '1000',
                // intentionally omit referral_bonus_field_agent_team_pct
            ])
            ->assertSessionHasErrors('referral_bonus_field_agent_team_pct');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function team_field_agent_pct_must_be_between_0_and_100(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => '200',
                'vendor_tier2_onboarding_fee' => '100',
                'vendor_tier1_commission_rate' => '12',
                'vendor_tier2_commission_rate' => '8',
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'vendor_onboarding_subsidy_pct' => '25',
                'referral_points_per_ghs' => '10',
                'referral_cashout_min_points' => '1000',
                'referral_bonus_field_agent_team_pct' => '150',
            ])
            ->assertSessionHasErrors('referral_bonus_field_agent_team_pct');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function team_field_agent_pct_round_trips_through_setting_get(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => '200',
                'vendor_tier2_onboarding_fee' => '100',
                'vendor_tier1_commission_rate' => '12',
                'vendor_tier2_commission_rate' => '8',
                'referral_bonus_customer_pct' => '15',
                'referral_bonus_vendor_pct' => '20',
                'referral_bonus_influencer_pct' => '25',
                'referral_bonus_field_agent_pct' => '30',
                'referral_bonus_employee_pct' => '20',
                'vendor_onboarding_subsidy_pct' => '25',
                'referral_points_per_ghs' => '10',
                'referral_cashout_min_points' => '1000',
                'referral_bonus_field_agent_team_pct' => '42',
            ])
            ->assertRedirect();

        \Illuminate\Support\Facades\Cache::flush();
        $this->assertEquals(42.0, (float) \App\Models\Setting::get('referral_bonus_field_agent_team_pct'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function migration_seeds_the_team_field_agent_pct_setting(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertEquals(35.0, (float) \App\Models\Setting::get('referral_bonus_field_agent_team_pct'));
    }
}
