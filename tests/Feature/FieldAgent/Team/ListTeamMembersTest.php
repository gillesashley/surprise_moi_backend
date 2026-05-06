<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ListTeamMembersTest extends TestCase
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

    public function test_lead_sees_only_own_members(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $ownMembers = User::factory()->teamMember($leadA)->count(2)->create();
        User::factory()->teamMember($leadB)->count(3)->create();

        $response = $this->actingAs($leadA)->get('/field-agent/team');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/team/index')
            ->has('members', 2)
            ->where('members.0.parent_user_id', $leadA->id)
        );
    }

    public function test_solo_field_agent_forbidden(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);

        $this->actingAs($solo)->get('/field-agent/team')->assertForbidden();
    }

    public function test_member_forbidden(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)->get('/field-agent/team')->assertForbidden();
    }
}
