<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateTeamMemberTest extends TestCase
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

    public function test_lead_creates_member_with_phone_as_password(): void
    {
        $lead = User::factory()->lead()->create();

        $response = $this->actingAs($lead)->post('/field-agent/team', [
            'name' => 'Member One',
            'email' => 'm1@example.com',
            'phone' => '0551234567',
            'location' => 'Accra',
        ]);

        $response->assertRedirect('/field-agent/team');

        $member = User::where('email', 'm1@example.com')->firstOrFail();
        $this->assertSame('field_agent', $member->role);
        $this->assertSame($lead->id, $member->parent_user_id);
        $this->assertFalse((bool) $member->is_team_field_agent);
        $this->assertTrue((bool) $member->is_active);
        $this->assertTrue((bool) $member->must_change_password);
        $this->assertSame('+233551234567', $member->phone);
        $this->assertSame('Accra', $member->location);
        $this->assertTrue(Hash::check('+233551234567', $member->password));
    }

    public function test_solo_field_agent_cannot_create_members(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);

        $this->actingAs($solo)->post('/field-agent/team', [
            'name' => 'X', 'email' => 'x@e.com', 'phone' => '0551112222', 'location' => 'A',
        ])->assertForbidden();
    }

    public function test_member_cannot_create_sub_members(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)->post('/field-agent/team', [
            'name' => 'X', 'email' => 'x@e.com', 'phone' => '0551112222', 'location' => 'A',
        ])->assertForbidden();
    }

    public function test_lead_can_add_existing_regular_user_as_team_member(): void
    {
        $lead = User::factory()->lead()->create();
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'phone' => '+233551112222',
            'role' => 'customer',
        ]);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'Updated Name',
                'email' => 'existing@example.com',
                'phone' => '0551112222',
                'location' => 'Kumasi',
            ])
            ->assertRedirect('/field-agent/team');

        $existing->refresh();
        $this->assertSame('field_agent', $existing->role);
        $this->assertSame($lead->id, $existing->parent_user_id);
        $this->assertFalse((bool) $existing->is_team_field_agent);
        $this->assertTrue((bool) $existing->is_active);
        $this->assertSame('Updated Name', $existing->name);
        $this->assertSame('Kumasi', $existing->location);
    }

    public function test_cannot_add_existing_team_member(): void
    {
        $lead = User::factory()->lead()->create();
        $otherLead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($otherLead)->create([
            'email' => 'member@example.com',
            'phone' => '+233551112222',
        ]);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'member@example.com', 'phone' => '0551112222', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_cannot_add_existing_lead_as_team_member(): void
    {
        $lead = User::factory()->lead()->create();
        $otherLead = User::factory()->lead()->create([
            'email' => 'otherlead@example.com',
            'phone' => '+233551112222',
        ]);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'otherlead@example.com', 'phone' => '0551112222', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_cannot_add_self_as_team_member(): void
    {
        $lead = User::factory()->lead()->create([
            'email' => 'lead@example.com',
            'phone' => '+233551234567',
        ]);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => $lead->name, 'email' => 'lead@example.com', 'phone' => '0551234567', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_phone_mismatch_with_different_user_is_rejected(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->create(['email' => 'alice@example.com', 'phone' => '+233551111111']);
        User::factory()->create(['email' => 'bob@example.com',   'phone' => '+233552222222']);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X',
                'email' => 'alice@example.com',
                'phone' => '0552222222', // bob's phone
                'location' => 'A',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_cannot_add_admin_as_team_member(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'phone' => '+233551112222',
        ]);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'admin@example.com', 'phone' => '0551112222', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_phone_format_rejected(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'x@e.com', 'phone' => 'not-a-number', 'location' => 'A',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_lead_can_render_new_member_form(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)->get('/field-agent/team/new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('field-agent/team/new'));
    }
}
