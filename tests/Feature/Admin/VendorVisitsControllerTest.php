<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorVisitsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_non_admin_cannot_access_admin_visits(): void
    {
        $this->actingAs(User::factory()->vendor()->create())
            ->get('/dashboard/vendor-visits')
            ->assertRedirect(); // EnsureDashboardAccess redirects non-admins
    }

    public function test_admin_sees_visits_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorVisit::factory()->escalated()->create();

        $this->actingAs($admin)->get('/dashboard/vendor-visits')->assertOk();
    }

    public function test_admin_can_open_a_visit_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->get("/dashboard/vendor-visits/{$visit->id}")
            ->assertOk();
    }

    public function test_admin_can_override_outcome(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/override", [
                'result' => VendorVisitStatus::Passed->value,
                'reason' => 'Reviewed photos; agent misclicked.',
            ])
            ->assertRedirect();

        $this->assertSame(VendorVisitStatus::Passed, $visit->fresh()->status);
    }

    public function test_override_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/override", [
                'result' => VendorVisitStatus::Passed->value,
                'reason' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_can_revoke_an_active_badge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/revoke", [
                'reason' => 'Customer complaint confirmed — business closed.',
            ])
            ->assertRedirect();

        $this->assertSame(VendorVisitStatus::Revoked, $visit->fresh()->status);
        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_revoke_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/revoke", ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
