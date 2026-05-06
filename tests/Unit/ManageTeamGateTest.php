<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManageTeamGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_manage_team(): void
    {
        $lead = User::factory()->lead()->create();
        $this->assertTrue(Gate::forUser($lead)->allows('manageTeam'));
    }

    public function test_solo_field_agent_cannot_manage_team(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);
        $this->assertFalse(Gate::forUser($solo)->allows('manageTeam'));
    }

    public function test_member_cannot_manage_team(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $this->assertFalse(Gate::forUser($member)->allows('manageTeam'));
    }

    public function test_admin_cannot_manage_team_via_this_gate(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertFalse(Gate::forUser($admin)->allows('manageTeam'));
    }
}
