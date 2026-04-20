<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\OverrideVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OverrideVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_override_failed_to_passed_and_badge_is_issued(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            visit: $visit,
            admin: $admin,
            newResult: VendorVisitStatus::Passed,
            reason: 'Reviewed photos; agent misclicked the location item.',
        );

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertSame(VendorVisitStatus::Passed, $result->admin_override_result);
        $this->assertSame($admin->id, $result->admin_override_by);
        $this->assertNotNull($result->admin_override_at);
        $this->assertNotNull($result->badge_issued_at);
        $this->assertNotNull($result->badge_expires_at);
    }

    public function test_admin_can_override_passed_to_failed_and_badge_window_is_cleared(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            $visit,
            $admin,
            VendorVisitStatus::Failed,
            'Customer reported the business doesn\'t exist.',
        );

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
        $this->assertNull($result->badge_issued_at);
        $this->assertNull($result->badge_expires_at);
    }

    public function test_computed_result_is_preserved_after_override(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            $visit,
            $admin,
            VendorVisitStatus::Passed,
            'reason',
        );

        $this->assertSame(VendorVisitStatus::Failed, $result->computed_result);
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(InvalidArgumentException::class);
        app(OverrideVendorVisit::class)->execute($visit, $admin, VendorVisitStatus::Passed, '');
    }

    public function test_only_passed_or_failed_overrides_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(InvalidArgumentException::class);
        app(OverrideVendorVisit::class)->execute($visit, $admin, VendorVisitStatus::Revoked, 'reason');
    }
}
