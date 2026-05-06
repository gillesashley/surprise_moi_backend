<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ShowTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        Config::set('inertia.testing.ensure_pages_exist', false);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_sees_own_member_with_onboarded_vendors(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'onboarded_by_user_id' => $member->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $response = $this->actingAs($lead)->get("/field-agent/team/{$member->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/team/show')
            ->where('member.id', $member->id)
            ->has('vendors', 1)
            ->where('vendors.0.id', $application->id)
            ->missing('vendors.0.amount')
        );
    }

    public function test_lead_cannot_view_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();

        $this->actingAs($leadA)->get("/field-agent/team/{$memberOfB->id}")->assertForbidden();
    }
}
