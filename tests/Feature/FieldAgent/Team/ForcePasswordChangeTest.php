<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
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

    public function test_member_with_must_change_password_redirected_from_dashboard(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)
            ->get('/field-agent/dashboard')
            ->assertRedirect('/settings/password');
    }

    public function test_member_can_reach_password_settings_to_change_it(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->get('/settings/password')->assertOk();
    }

    public function test_member_can_log_out_without_redirect_loop(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->post('/logout')->assertRedirect();
    }

    public function test_password_change_clears_must_change_password_flag(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create([
            'password' => Hash::make('+233551234567'),
        ]);

        $this->actingAs($member)->put('/settings/password', [
            'current_password' => '+233551234567',
            'password' => 'NewPass!9876',
            'password_confirmation' => 'NewPass!9876',
        ])->assertRedirect();

        $this->assertFalse((bool) $member->fresh()->must_change_password);
    }

    public function test_lead_without_flag_is_not_redirected(): void
    {
        $lead = User::factory()->lead()->create(['must_change_password' => false]);

        $this->actingAs($lead)->get('/field-agent/dashboard')->assertOk();
    }
}
