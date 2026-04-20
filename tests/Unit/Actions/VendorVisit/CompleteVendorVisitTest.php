<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFilledVisit(bool $hasCert = false, bool $hasTin = false)
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => $hasCert,
            'tin_number' => $hasTin ? 'C1234567890' : null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);

        $visit->items()->update(['passed' => true]);

        return $visit->fresh('items');
    }

    public function test_all_critical_pass_plus_photos_yields_passed_with_12_month_expiry(): void
    {
        $visit = $this->makeFilledVisit();
        $before = now();

        $result = app(CompleteVendorVisit::class)->execute($visit);

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertSame(VendorVisitStatus::Passed, $result->computed_result);
        $this->assertNotNull($result->submitted_at);
        $this->assertNotNull($result->badge_issued_at);
        $this->assertTrue(
            $result->badge_expires_at->greaterThanOrEqualTo($before->copy()->addMonths(12))
        );
    }

    public function test_escalated_always_yields_submitted(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['escalated' => true]);

        $result = app(CompleteVendorVisit::class)->execute($visit);

        $this->assertSame(VendorVisitStatus::Submitted, $result->status);
        $this->assertNull($result->badge_issued_at);
    }

    public function test_failing_any_critical_item_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'identity.person_matches_ghana_card')
            ->update(['passed' => false]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
        $this->assertNull($result->badge_issued_at);
    }

    public function test_failing_informational_alone_still_yields_passed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'financial.momo_test_received')
            ->update(['passed' => false]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertNotNull($result->badge_issued_at);
    }

    public function test_missing_storefront_photo_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['storefront_photo_path' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_missing_owner_photo_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['owner_photo_path' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_any_item_left_unanswered_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'physical.location_is_real')
            ->update(['passed' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_submitting_a_terminal_visit_is_a_noop(): void
    {
        $visit = $this->makeFilledVisit();
        $completed = app(CompleteVendorVisit::class)->execute($visit);
        $firstSubmittedAt = $completed->submitted_at;

        $again = app(CompleteVendorVisit::class)->execute($completed->fresh('items'));

        $this->assertSame(VendorVisitStatus::Passed, $again->status);
        $this->assertTrue($firstSubmittedAt->equalTo($again->submitted_at));
    }
}
