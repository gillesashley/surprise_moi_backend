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
}
