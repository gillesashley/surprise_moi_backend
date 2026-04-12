<?php

namespace Tests\Feature\Api\V1;

use App\Models\ReferralCode;
use App\Models\ReferralMilestoneReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReferralControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_summary(): void
    {
        $response = $this->getJson('/api/v1/me/referral-summary');

        $response->assertStatus(401);
    }

    public function test_first_call_creates_referral_code_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->assertDatabaseMissing('referral_codes', [
            'influencer_id' => $customer->id,
        ]);

        $response = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'referral_code',
                    'referral_points',
                    'next_milestone',
                    'previous_milestone',
                    'progress_to_next',
                    'milestones',
                    'total_referrals',
                    'pending_rewards',
                    'milestone_first',
                    'milestone_increment',
                ],
            ]);

        $this->assertDatabaseHas('referral_codes', [
            'influencer_id' => $customer->id,
            'is_active' => true,
        ]);
    }

    public function test_subsequent_call_reuses_existing_code(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $first = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');
        $second = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');

        $this->assertEquals(
            $first->json('data.referral_code'),
            $second->json('data.referral_code'),
            'Each user should keep a single referral code across calls'
        );
        $this->assertEquals(
            1,
            ReferralCode::where('influencer_id', $customer->id)->count()
        );
    }

    public function test_summary_reports_zero_points_and_first_milestone_next(): void
    {
        config(['referral.milestone_first' => 1000]);
        config(['referral.milestone_increment' => 5000]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'referral_points' => 0,
        ]);

        $response = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');

        $response->assertOk()
            ->assertJsonPath('data.referral_points', 0)
            ->assertJsonPath('data.next_milestone', 1000)
            ->assertJsonPath('data.previous_milestone', 0);

        // progress_to_next may serialize as int 0 or float 0.0 — compare loosely.
        $this->assertEquals(0, $response->json('data.progress_to_next'));

        $milestones = $response->json('data.milestones');
        $this->assertGreaterThanOrEqual(6, count($milestones));
        $this->assertEquals(1000, $milestones[0]['threshold']);
        $this->assertEquals('upcoming', $milestones[0]['status']);
    }

    public function test_summary_reports_progress_between_thresholds(): void
    {
        config(['referral.milestone_first' => 1000]);
        config(['referral.milestone_increment' => 5000]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'referral_points' => 3000,
        ]);

        // Simulate the 1000-point milestone row created previously.
        ReferralMilestoneReward::create([
            'user_id' => $customer->id,
            'threshold' => 1000,
            'points_at_milestone' => 1000,
            'status' => ReferralMilestoneReward::STATUS_PENDING,
        ]);

        $response = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');

        $response->assertOk()
            ->assertJsonPath('data.referral_points', 3000)
            ->assertJsonPath('data.previous_milestone', 1000)
            ->assertJsonPath('data.next_milestone', 5000);

        // (3000 - 1000) / (5000 - 1000) = 0.5
        $this->assertEquals(0.5, $response->json('data.progress_to_next'));

        $milestones = collect($response->json('data.milestones'))->keyBy('threshold');
        $this->assertEquals('earned', $milestones[1000]['status']);
        $this->assertEquals('upcoming', $milestones[5000]['status']);
        $this->assertEquals(1, $response->json('data.pending_rewards'));
    }

    public function test_summary_marks_fulfilled_milestone_correctly(): void
    {
        config(['referral.milestone_first' => 1000]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'referral_points' => 2000,
        ]);

        ReferralMilestoneReward::create([
            'user_id' => $customer->id,
            'threshold' => 1000,
            'points_at_milestone' => 1000,
            'status' => ReferralMilestoneReward::STATUS_FULFILLED,
            'fulfilled_at' => now(),
            'reward_description' => 'Airtime GHS 20',
        ]);

        $response = $this->actingAs($customer)->getJson('/api/v1/me/referral-summary');

        $response->assertOk();
        $milestones = collect($response->json('data.milestones'))->keyBy('threshold');
        $this->assertEquals('fulfilled', $milestones[1000]['status']);
        $this->assertEquals('Airtime GHS 20', $milestones[1000]['reward_description']);
        $this->assertEquals(0, $response->json('data.pending_rewards'));
    }

    public function test_any_role_can_access_summary(): void
    {
        foreach (['customer', 'vendor', 'influencer', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->getJson('/api/v1/me/referral-summary');

            $response->assertOk();
        }
    }
}
