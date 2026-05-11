<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTeamStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_state_creates_team_field_agent_with_no_parent(): void
    {
        $lead = User::factory()->lead()->create();

        $this->assertSame('field_agent', $lead->role);
        $this->assertTrue($lead->is_team_field_agent);
        $this->assertNull($lead->parent_user_id);
        $this->assertTrue((bool) $lead->is_active);
        $this->assertFalse((bool) $lead->must_change_password);
    }

    public function test_team_member_state_creates_member_under_a_lead(): void
    {
        $lead = User::factory()->lead()->create();

        $member = User::factory()->teamMember($lead)->create();

        $this->assertSame('field_agent', $member->role);
        $this->assertFalse((bool) $member->is_team_field_agent);
        $this->assertSame($lead->id, $member->parent_user_id);
        $this->assertTrue((bool) $member->is_active);
        $this->assertFalse((bool) $member->must_change_password);
    }
}
