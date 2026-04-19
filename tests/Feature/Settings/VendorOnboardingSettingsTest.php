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
        // Load and run the migration's up() directly via its absolute path so the seeded
        // rows are guaranteed to exist within the test transaction regardless of how
        // the app resolves database_path() (worktree vs. main repo symlink).
        $migration = require __DIR__.'/../../../database/migrations/2026_04_18_120000_seed_referral_program_redesign_settings.php';
        $migration->up();

        // Flush the array-driver cache so Setting::get() re-reads fresh from DB.
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertSame('25.00', \App\Models\Setting::get('vendor_onboarding_subsidy_pct'));
        $this->assertSame('10', \App\Models\Setting::get('referral_points_per_ghs'));
        $this->assertSame('1000', \App\Models\Setting::get('referral_cashout_min_points'));
    }
}
