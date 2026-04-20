<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\RevokeFieldVerificationBadge;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RevokeFieldVerificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_active_badge_and_clears_cached_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        $result = app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, 'fraud discovered');

        $this->assertSame(VendorVisitStatus::Revoked, $result->status);
        $this->assertSame('fraud discovered', $result->admin_override_reason);
        $this->assertSame($admin->id, $result->admin_override_by);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_cannot_revoke_a_non_passed_visit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(RuntimeException::class);
        app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, 'reason');
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, '');
    }
}
