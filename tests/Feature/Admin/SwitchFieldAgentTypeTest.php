<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchFieldAgentTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->session(['user_management.verified_at' => time()]);
    }

    public function test_admin_switches_individual_field_agent_to_team(): void
    {
        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => false,
        ]);

        $this->actingAs($this->admin)
            ->from('/dashboard/users?role=field_agent')
            ->post("/dashboard/users/{$agent->id}/switch-field-agent-type")
            ->assertRedirect('/dashboard/users?role=field_agent')
            ->assertSessionHas('success');

        $this->assertTrue($agent->fresh()->is_team_field_agent);
    }

    public function test_admin_switches_team_field_agent_back_to_individual(): void
    {
        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);

        $this->actingAs($this->admin)
            ->from('/dashboard/users?role=field_agent')
            ->post("/dashboard/users/{$agent->id}/switch-field-agent-type")
            ->assertRedirect('/dashboard/users?role=field_agent')
            ->assertSessionHas('success');

        $this->assertFalse($agent->fresh()->is_team_field_agent);
    }

    public function test_switch_is_rejected_for_non_field_agent_user(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($this->admin)
            ->from('/dashboard/users')
            ->post("/dashboard/users/{$customer->id}/switch-field-agent-type")
            ->assertRedirect('/dashboard/users')
            ->assertSessionHas('error');

        $this->assertFalse($customer->fresh()->is_team_field_agent);
    }

    public function test_non_admin_cannot_switch_field_agent_type(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => false,
        ]);

        $response = $this->actingAs($customer)
            ->post("/dashboard/users/{$agent->id}/switch-field-agent-type");

        $response->assertRedirect(route('login'));
        $this->assertFalse($agent->fresh()->is_team_field_agent);
    }

    public function test_switch_records_audit_log(): void
    {
        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => false,
        ]);

        $this->actingAs($this->admin)
            ->from('/dashboard/users?role=field_agent')
            ->post("/dashboard/users/{$agent->id}/switch-field-agent-type");

        $log = ActivityLog::where('event', 'field_agent.type_switched')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($agent->id, $log->subject_id);
        $this->assertSame('individual', $log->properties['extra']['old_type']);
        $this->assertSame('team', $log->properties['extra']['new_type']);
    }
}
