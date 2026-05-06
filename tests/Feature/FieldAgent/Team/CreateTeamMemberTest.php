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
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->post('/field-agent/team', [
            'name' => 'X', 'email' => 'x@e.com', 'phone' => '0551112222', 'location' => 'A',
        ])->assertForbidden();
    }

    public function test_duplicate_email_or_phone_returns_validation_error(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '+233551112222']);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'taken@example.com', 'phone' => '0552223333', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'fresh@example.com', 'phone' => '0551112222', 'location' => 'A',
            ])
            ->assertSessionHasErrors('phone');
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
}
