<?php

namespace Tests\Unit;

use App\Models\FieldAgentApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentApplicationTeamFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_team_defaults_to_false(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertFalse($app->is_team);
        $this->assertFalse($app->isTeam());
    }

    public function test_is_team_can_be_set_via_factory_state(): void
    {
        $app = FieldAgentApplication::factory()->team()->create();

        $this->assertTrue($app->is_team);
        $this->assertTrue($app->isTeam());
    }
}
