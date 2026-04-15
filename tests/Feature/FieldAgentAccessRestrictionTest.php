<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_agent_is_redirected_away_from_admin_routes(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->actingAs($agent)
            ->get('/dashboard/field-agent-applications')
            ->assertRedirect(route('field-agent.dashboard'));
    }

    public function test_field_agent_is_redirected_away_from_admin_dashboard(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertRedirect(route('field-agent.dashboard'));
    }

    public function test_field_agent_can_access_own_dashboard(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->actingAs($agent)
            ->get('/field-agent/dashboard')
            ->assertOk();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard/field-agent-applications')
            ->assertOk();
    }

    public function test_admin_redirected_away_from_field_agent_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/field-agent/dashboard')
            ->assertRedirect(route('dashboard'));
    }
}
