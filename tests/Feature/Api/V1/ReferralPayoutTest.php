<?php

namespace Tests\Feature\Api\V1;

use App\Models\PayoutRequest;
use App\Models\ReferralMilestoneReward;
use App\Models\User;
use App\Models\UserPayoutDetail;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedDetail(User $user): UserPayoutDetail
    {
        return UserPayoutDetail::factory()->create(['user_id' => $user->id]);
    }

    public function test_summary_exposes_payout_fields(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        $this->verifiedDetail($user);

        $this->actingAs($user)
            ->getJson('/api/v1/me/referral-summary')
            ->assertStatus(200)
            ->assertJsonPath('data.available_payout_amount', 100)
            ->assertJsonPath('data.next_unlock_threshold', 1000)
            ->assertJsonPath('data.can_request_payout', true)
            ->assertJsonPath('data.pending_payout', null);
    }

    public function test_cannot_request_payout_without_payout_details(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);

        $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please save your mobile money details before requesting a payout.');
    }

    public function test_cannot_request_payout_below_first_milestone(): void
    {
        $user = User::factory()->create(['referral_points' => 500]);
        $this->verifiedDetail($user);

        $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(422);
    }

    public function test_creates_payout_request_when_eligible(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        $detail = $this->verifiedDetail($user);

        $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(201)
            ->assertJsonPath('data.source', 'referral_milestone')
            ->assertJsonPath('data.referral_milestone_threshold', 1000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'user_payout_detail_id' => $detail->id,
            'amount' => 100.00,
            'points_deducted' => 1000,
            'status' => 'pending',
        ]);
    }

    public function test_second_concurrent_request_is_rejected(): void
    {
        $user = User::factory()->create(['referral_points' => 5000]);
        $this->verifiedDetail($user);

        $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(422);
    }

    public function test_cancel_pending_request(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        $this->verifiedDetail($user);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(201);

        $id = $response->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/me/referral-payout-requests/{$id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_non_pending_request_returns_409(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        $this->verifiedDetail($user);

        $payout = PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'processing',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/me/referral-payout-requests/{$payout->id}/cancel")
            ->assertStatus(409);
    }

    public function test_mark_paid_fulfills_milestone_reward(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        ReferralMilestoneReward::create([
            'user_id' => $user->id,
            'threshold' => 1000,
            'points_at_milestone' => 1000,
            'status' => 'pending',
            'reward_type' => 'cash_payout',
        ]);
        $detail = $this->verifiedDetail($user);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/referral-payout-requests', [])
            ->assertStatus(201);

        $payoutId = $response->json('data.id');
        $payout = PayoutRequest::find($payoutId);

        // Simulate admin marking paid via the existing service.
        app(PayoutService::class)->markAsPaid($payout);

        $this->assertDatabaseHas('referral_milestone_rewards', [
            'user_id' => $user->id,
            'threshold' => 1000,
            'status' => 'fulfilled',
            'payout_request_id' => $payout->id,
        ]);
    }

    public function test_user_can_list_their_referral_payouts(): void
    {
        $user = User::factory()->create();
        PayoutRequest::factory()->count(3)->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'paid',
        ]);
        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'vendor_earnings', // must NOT appear
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/me/referral-payout-requests')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }
}
