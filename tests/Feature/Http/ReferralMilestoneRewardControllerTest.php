<?php

namespace Tests\Feature\Http;

use App\Models\ReferralMilestoneReward;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralMilestoneRewardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_view_milestone_rewards_index(): void
    {
        ReferralMilestoneReward::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/dashboard/referral-milestones');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('referral-milestones/index')
            ->has('rewards.data', 3)
        );
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/dashboard/referral-milestones');

        // EnsureDashboardAccess middleware may redirect before policy fires.
        $this->assertTrue(
            in_array($response->status(), [302, 403], true),
            "Expected 302 or 403, got {$response->status()}"
        );
    }

    public function test_admin_can_fulfill_pending_milestone(): void
    {
        $reward = ReferralMilestoneReward::factory()->create([
            'status' => ReferralMilestoneReward::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/referral-milestones/{$reward->id}/fulfill", [
                'reward_type' => 'gift',
                'reward_description' => 'Power bank',
                'reward_value' => 150.00,
                'admin_notes' => 'Delivered via courier',
            ]);

        $response->assertRedirect();
        $fresh = $reward->fresh();
        $this->assertEquals(ReferralMilestoneReward::STATUS_FULFILLED, $fresh->status);
        $this->assertEquals('gift', $fresh->reward_type);
        $this->assertEquals('Power bank', $fresh->reward_description);
        $this->assertEquals(150.00, (float) $fresh->reward_value);
        $this->assertEquals($this->admin->id, $fresh->fulfilled_by);
        $this->assertNotNull($fresh->fulfilled_at);
    }

    public function test_fulfill_accepts_empty_optional_fields(): void
    {
        $reward = ReferralMilestoneReward::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/referral-milestones/{$reward->id}/fulfill", []);

        $response->assertRedirect();
        $this->assertEquals(ReferralMilestoneReward::STATUS_FULFILLED, $reward->fresh()->status);
    }

    public function test_fulfill_rejects_non_numeric_reward_value(): void
    {
        $reward = ReferralMilestoneReward::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/referral-milestones/{$reward->id}/fulfill", [
                'reward_value' => 'not-a-number',
            ]);

        $response->assertSessionHasErrors('reward_value');
    }

    public function test_non_admin_cannot_fulfill(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $reward = ReferralMilestoneReward::factory()->create();

        $response = $this->actingAs($customer)
            ->patch("/dashboard/referral-milestones/{$reward->id}/fulfill", [
                'reward_type' => 'gift',
            ]);

        // EnsureDashboardAccess middleware may redirect before policy fires.
        $this->assertTrue(
            in_array($response->status(), [302, 403], true),
            "Expected 302 or 403, got {$response->status()}"
        );
    }
}
