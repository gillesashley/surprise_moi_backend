<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
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

    public function test_lead_shared_props_carry_team_fields(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)->get('/field-agent/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_team_field_agent', true)
                ->where('auth.user.parent_user_id', null)
                ->where('auth.user.must_change_password', false)
                ->where('auth.user.is_active', true)
            );
    }

    public function test_member_shared_props_show_lead_id_and_password_flag(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)->get('/field-agent/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_team_field_agent', false)
                ->where('auth.user.parent_user_id', $lead->id)
                ->where('auth.user.must_change_password', false)
            );
    }
}
