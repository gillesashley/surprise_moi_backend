<?php

namespace Tests\Feature\Observers;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cached-badge / visit-item flow was replaced by the questionnaire
 * refactor (2026_04_21_163303_refactor_vendor_visits_for_questionnaires),
 * so these tests cover the current VendorVisit contract instead.
 */
class VendorVisitObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_result_falls_back_to_computed_result(): void
    {
        $visit = VendorVisit::factory()->passed()->create();

        $this->assertSame(VendorVisitStatus::Passed, $visit->status);
        $this->assertSame(VendorVisitStatus::Passed, $visit->computed_result);
        $this->assertSame(VendorVisitStatus::Passed, $visit->effectiveResult());
    }

    public function test_effective_result_prefers_admin_override(): void
    {
        $visit = VendorVisit::factory()->passed()->create([
            'admin_override_result' => VendorVisitStatus::Failed->value,
        ]);

        $this->assertSame(VendorVisitStatus::Failed, $visit->effectiveResult());
    }

    public function test_visit_exposes_vendor_and_agent_relations(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();

        $visit = VendorVisit::factory()->create([
            'vendor_user_id' => $vendor->id,
            'field_agent_user_id' => $agent->id,
        ]);

        $this->assertTrue($visit->vendor->is($vendor));
        $this->assertTrue($visit->fieldAgent->is($agent));
    }

    public function test_questionnaire_fields_persist(): void
    {
        $visit = VendorVisit::factory()->create([
            'ghana_card_number' => 'GHA-123456789-1',
            'has_shop' => true,
            'shop_location' => 'Osu',
        ]);

        $visit->refresh();

        $this->assertSame('GHA-123456789-1', $visit->ghana_card_number);
        $this->assertTrue($visit->has_shop);
        $this->assertSame('Osu', $visit->shop_location);
    }
}
