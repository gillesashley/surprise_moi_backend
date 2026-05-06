<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VendorAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_can_start_visit_under_leads_referral_code_and_attribution_set(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($member)
            ->post("/field-agent/visits/{$application->id}/start")
            ->assertRedirect();

        $this->assertSame($member->id, $application->fresh()->onboarded_by_user_id);
        $this->assertDatabaseHas('vendor_visits', [
            'vendor_application_id' => $application->id,
            'field_agent_user_id' => $member->id,
        ]);
    }

    public function test_member_of_other_lead_cannot_start_visit(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $codeA = ReferralCode::factory()->create(['influencer_id' => $leadA->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $codeA->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($memberOfB)
            ->post("/field-agent/visits/{$application->id}/start")
            ->assertForbidden();
    }

    public function test_first_claim_wins_onboarded_by_user_id_does_not_overwrite(): void
    {
        $lead = User::factory()->lead()->create();
        $memberA = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $memberB = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($memberA)->post("/field-agent/visits/{$application->id}/start");
        $this->actingAs($memberB)->post("/field-agent/visits/{$application->id}/start");

        $this->assertSame($memberA->id, $application->fresh()->onboarded_by_user_id);
    }
}
