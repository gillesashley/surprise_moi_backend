<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTeamFieldAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_false(): void
    {
        $user = User::factory()->create(['role' => 'field_agent']);

        $this->assertFalse($user->is_team_field_agent);
        $this->assertFalse($user->isTeamFieldAgent());
    }

    public function test_team_field_agent_returns_true_when_role_and_flag_match(): void
    {
        $user = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);

        $this->assertTrue($user->isTeamFieldAgent());
    }

    public function test_non_field_agent_role_short_circuits_to_false(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_team_field_agent' => true,
        ]);

        $this->assertFalse(
            $user->isTeamFieldAgent(),
            'Even with the flag set, a non-field-agent must not report as team'
        );
    }
}
