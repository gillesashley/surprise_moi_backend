<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\TeamMemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_view_and_update_own_member(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $policy = new TeamMemberPolicy;

        $this->assertTrue($policy->view($lead, $member));
        $this->assertTrue($policy->update($lead, $member));
    }

    public function test_lead_cannot_view_or_update_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();
        $policy = new TeamMemberPolicy;

        $this->assertFalse($policy->view($leadA, $memberOfB));
        $this->assertFalse($policy->update($leadA, $memberOfB));
    }

    public function test_solo_or_member_cannot_view_or_update(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);
        $policy = new TeamMemberPolicy;

        $this->assertFalse($policy->view($solo, $member));
        $this->assertFalse($policy->update($member, $member));
    }
}
