<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_vendor_onboarding_stats(): void
    {
        $this->get(route('vendor-onboarding-stats'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_access_vendor_onboarding_stats(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('vendor-onboarding-stats'))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_vendor_onboarding_stats(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('vendor-onboarding-stats'))->assertOk();
    }

    public function test_stats_show_correct_vendor_counts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // Create tier 1 vendors
        User::factory()->count(3)->create(['role' => 'vendor', 'vendor_tier' => 1]);
        // Create tier 2 vendors
        User::factory()->count(2)->create(['role' => 'vendor', 'vendor_tier' => 2]);
        // Create a non-vendor (should not be counted)
        User::factory()->create(['role' => 'customer']);

        $response = $this->get(route('vendor-onboarding-stats'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-onboarding-stats/index')
            ->where('stats.total_vendors', 5)
            ->where('stats.tier1_vendors', 3)
            ->where('stats.tier2_vendors', 2)
        );
    }

    public function test_stats_show_correct_onboarding_fees(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // Approved tier 1 application (registered vendor, has_business_certificate = true)
        $tier1User = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        VendorApplication::factory()
            ->for($tier1User)
            ->registeredVendor()
            ->approved()
            ->create(['onboarding_fee' => 100.00]);

        // Approved tier 2 application (unregistered vendor, has_business_certificate = false)
        $tier2User = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 2]);
        VendorApplication::factory()
            ->for($tier2User)
            ->unregisteredVendor()
            ->approved()
            ->create(['onboarding_fee' => 50.00]);

        // Pending application (should NOT count toward fees)
        $pendingUser = User::factory()->create(['role' => 'customer']);
        VendorApplication::factory()
            ->for($pendingUser)
            ->registeredVendor()
            ->pending()
            ->create(['onboarding_fee' => 100.00]);

        $response = $this->get(route('vendor-onboarding-stats'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_onboarding_fees', '150.00')
            ->where('stats.tier1_onboarding_fees', '100.00')
            ->where('stats.tier2_onboarding_fees', '50.00')
        );
    }

    public function test_stats_handle_zero_vendors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('vendor-onboarding-stats'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_vendors', 0)
            ->where('stats.tier1_vendors', 0)
            ->where('stats.tier2_vendors', 0)
            ->where('stats.total_onboarding_fees', '0.00')
            ->where('stats.tier1_onboarding_fees', '0.00')
            ->where('stats.tier2_onboarding_fees', '0.00')
        );
    }
}
