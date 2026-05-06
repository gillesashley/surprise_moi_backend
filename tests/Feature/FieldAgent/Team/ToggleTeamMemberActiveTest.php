<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ToggleTeamMemberActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_deactivates_then_reactivates_member(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", ['is_active' => false])
            ->assertRedirect("/field-agent/team/{$member->id}");
        $this->assertFalse((bool) $member->fresh()->is_active);

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", ['is_active' => true])
            ->assertRedirect("/field-agent/team/{$member->id}");
        $this->assertTrue((bool) $member->fresh()->is_active);
    }

    public function test_lead_cannot_toggle_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();

        $this->actingAs($leadA)->patch("/field-agent/team/{$memberOfB->id}", ['is_active' => false])
            ->assertForbidden();
        $this->assertTrue((bool) $memberOfB->fresh()->is_active);
    }

    public function test_other_fields_are_ignored(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create([
            'name' => 'Original',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", [
            'is_active' => false,
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
            'parent_user_id' => 99999,
        ])->assertRedirect();

        $fresh = $member->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame('original@example.com', $fresh->email);
        $this->assertSame($lead->id, $fresh->parent_user_id);
    }
}
