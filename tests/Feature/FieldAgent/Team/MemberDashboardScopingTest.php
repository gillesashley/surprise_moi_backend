<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MemberDashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_forbidden_on_money_pages(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        foreach (['/field-agent/earnings', '/field-agent/payouts', '/field-agent/targets', '/field-agent/verification', '/field-agent/terms'] as $path) {
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    public function test_lead_can_access_money_pages(): void
    {
        $lead = User::factory()->lead()->create();

        foreach (['/field-agent/earnings', '/field-agent/payouts', '/field-agent/targets'] as $path) {
            $this->actingAs($lead)->get($path)->assertOk();
        }
    }
}
