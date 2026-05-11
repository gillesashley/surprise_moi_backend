<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_is_not_redirected_from_dashboard(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)
            ->get('/field-agent/dashboard')
            ->assertOk();
    }

    public function test_member_can_still_reach_password_settings(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->get('/settings/password')->assertOk();
    }

    public function test_member_can_log_out(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->post('/logout')->assertRedirect();
    }

    public function test_lead_without_flag_is_not_redirected(): void
    {
        $lead = User::factory()->lead()->create(['must_change_password' => false]);

        $this->actingAs($lead)->get('/field-agent/dashboard')->assertOk();
    }
}
