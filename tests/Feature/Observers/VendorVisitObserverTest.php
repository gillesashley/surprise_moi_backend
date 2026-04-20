<?php

namespace Tests\Feature\Observers;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorVisitObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function passingVisit(User $vendor, User $agent): VendorVisit
    {
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);
        $visit->items()->update(['passed' => true]);

        return app(CompleteVendorVisit::class)->execute($visit->fresh('items'));
    }

    public function test_passing_visit_sets_cached_columns_on_vendor(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();

        $visit = $this->passingVisit($vendor, $agent);

        $vendor->refresh();

        $this->assertNotNull($vendor->field_verified_at);
        $this->assertNotNull($vendor->field_verified_until);
        $this->assertSame(
            $visit->badge_expires_at->format('Y-m-d H:i:s'),
            $vendor->field_verified_until->format('Y-m-d H:i:s')
        );
    }

    public function test_revoked_visit_clears_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();
        $visit = $this->passingVisit($vendor, $agent);

        $visit->update(['status' => VendorVisitStatus::Revoked->value]);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_later_passing_visit_extends_cached_expiry(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agentA = User::factory()->fieldAgent()->create();
        $agentB = User::factory()->fieldAgent()->create();

        $this->travelTo(now()->subMonths(3));
        $this->passingVisit($vendor, $agentA);
        $vendor->refresh();
        $originalExpiry = $vendor->field_verified_until;

        $this->travelTo(now()->addMonths(3));
        $this->passingVisit($vendor, $agentB);

        $vendor->refresh();
        $this->assertTrue($vendor->field_verified_until->greaterThan($originalExpiry));
    }

    public function test_failed_visit_does_not_touch_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();

        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);
        $visit->items()->where('criticality', 'critical')->first()->update(['passed' => false]);
        $visit->items()->where('passed', null)->update(['passed' => true]);

        app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
    }

    public function test_override_from_passed_to_failed_clears_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();
        $visit = $this->passingVisit($vendor, $agent);

        $visit->update([
            'status' => VendorVisitStatus::Failed->value,
            'admin_override_result' => VendorVisitStatus::Failed->value,
        ]);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
    }
}
