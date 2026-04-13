<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkGenerateReferralCodeTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    public function test_bulk_generate_creates_codes_for_users_without_active_code(): void
    {
        User::factory()->count(3)->vendor()->create();

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(3, $count);
        $this->assertEquals(3, ReferralCode::where('is_active', true)->count());
    }

    public function test_bulk_generate_skips_users_with_existing_active_code(): void
    {
        $vendorWithCode = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendorWithCode->id,
            'is_active' => true,
        ]);

        User::factory()->count(2)->vendor()->create();

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(2, $count);
    }

    public function test_bulk_generate_uses_correct_prefix_for_role(): void
    {
        User::factory()->vendor()->create();

        $this->service->bulkGenerateCodes('vendor');

        $vendorCode = ReferralCode::latest()->first();
        $this->assertStringStartsWith('VD-', $vendorCode->code);
    }

    public function test_bulk_generate_returns_zero_when_all_users_have_codes(): void
    {
        $vendor = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendor->id,
            'is_active' => true,
        ]);

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(0, $count);
    }

    public function test_bulk_generate_handles_customer_role(): void
    {
        User::factory()->count(2)->create(['role' => 'customer']);

        $count = $this->service->bulkGenerateCodes('customer');

        $this->assertEquals(2, $count);

        ReferralCode::all()->each(function ($code) {
            $this->assertStringStartsWith('CU-', $code->code);
        });
    }

    public function test_bulk_generate_preview_endpoint_returns_correct_counts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        User::factory()->count(5)->vendor()->create();
        $vendorWithCode = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendorWithCode->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate/preview', [
            'role' => 'vendor',
        ]);

        $response->assertOk()
            ->assertJson([
                'total' => 6,
                'with_code' => 1,
                'without_code' => 5,
                'prefix' => 'VD-',
            ]);
    }

    public function test_bulk_generate_endpoint_creates_codes_and_returns_count(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->count(3)->vendor()->create();

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'vendor',
        ]);

        $response->assertOk()
            ->assertJson([
                'generated' => 3,
            ]);

        $this->assertEquals(3, ReferralCode::count());
    }

    public function test_bulk_generate_endpoint_blocks_non_admin(): void
    {
        // A vendor cannot access /dashboard at all — the EnsureDashboardAccess
        // middleware redirects them before the controller is reached.
        $vendor = User::factory()->vendor()->create();

        $response = $this->actingAs($vendor)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'vendor',
        ]);

        // postJson sends Accept: application/json so we expect 403 from middleware
        // or a redirect; assert it is NOT a 2xx success response.
        $this->assertTrue(
            $response->status() >= 300,
            "Expected a blocked response but got HTTP {$response->status()}"
        );
    }

    public function test_bulk_generate_endpoint_rejects_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'admin',
        ]);

        $response->assertUnprocessable();
    }
}
