<?php

namespace Tests\Feature\Console;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillFieldAgentReferralCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_codes_for_agents_missing_one(): void
    {
        $agentA = User::factory()->create(['role' => 'field_agent']);
        $agentB = User::factory()->create(['role' => 'field_agent']);
        $agentC = User::factory()->create(['role' => 'field_agent']);
        $existingCode = new ReferralCode(['influencer_id' => $agentC->id, 'is_active' => true]);
        $existingCode->prefix = ReferralCode::getPrefixForRole('field_agent');
        $existingCode->save();

        $this->artisan('field-agents:backfill-referral-codes')
            ->assertSuccessful();

        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentA->id]);
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentB->id]);
        $this->assertSame(3, ReferralCode::count());
    }

    public function test_it_skips_non_field_agent_users(): void
    {
        User::factory()->create(['role' => 'customer']);
        User::factory()->create(['role' => 'vendor']);
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')
            ->assertSuccessful();

        $this->assertSame(1, ReferralCode::count());
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agent->id]);
    }

    public function test_it_is_idempotent(): void
    {
        User::factory()->create(['role' => 'field_agent']);
        User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();
        $firstCount = ReferralCode::count();

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();
        $secondCount = ReferralCode::count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_it_generates_fa_prefixed_codes(): void
    {
        User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();

        $this->assertStringStartsWith('FA-', ReferralCode::first()->code);
    }
}
