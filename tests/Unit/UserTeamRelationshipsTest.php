<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTeamRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_has_team_members_relation(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->teamMember($lead)->count(2)->create();

        $this->assertCount(2, $lead->teamMembers);
        $this->assertTrue($lead->isLead());
        $this->assertFalse($lead->isTeamMember());
    }

    public function test_member_has_lead_relation(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->assertTrue($member->lead->is($lead));
        $this->assertFalse($member->isLead());
        $this->assertTrue($member->isTeamMember());
    }

    public function test_is_active_and_must_change_password_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => 1, 'must_change_password' => 0]);

        $this->assertSame(true, $user->is_active);
        $this->assertSame(false, $user->must_change_password);
    }
}
