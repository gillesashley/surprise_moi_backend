<?php

namespace Tests\Unit;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserReferralPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_amount_is_points_divided_by_ten_minus_paid_and_pending(): void
    {
        $user = User::factory()->create(['referral_points' => 5000]); // = GHS 500

        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'paid',
            'amount' => 100,
        ]);
        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'pending',
            'amount' => 50,
        ]);

        $this->assertSame(350.0, $user->availableReferralPayoutAmount());
    }

    public function test_next_unlock_threshold_is_first_milestone_when_nothing_paid(): void
    {
        $user = User::factory()->create();
        $this->assertSame(1000, $user->nextReferralUnlockThreshold());
    }

    public function test_next_unlock_threshold_advances_after_payout(): void
    {
        $user = User::factory()->create(['referral_points' => 5000]);
        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'paid',
            'amount' => 100, // = 1000 points cashed out
        ]);

        $this->assertSame(5000, $user->nextReferralUnlockThreshold());
    }

    public function test_cannot_request_payout_when_points_below_next_threshold(): void
    {
        $user = User::factory()->create(['referral_points' => 800]);
        $this->assertFalse($user->canRequestReferralPayout());
    }

    public function test_can_request_payout_when_points_reach_first_milestone(): void
    {
        $user = User::factory()->create(['referral_points' => 1000]);
        $this->assertTrue($user->canRequestReferralPayout());
    }

    public function test_cannot_request_payout_while_pending_request_exists(): void
    {
        $user = User::factory()->create(['referral_points' => 5000]);
        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'pending',
            'amount' => 100,
        ]);

        $this->assertFalse($user->canRequestReferralPayout());
    }

    public function test_cannot_request_payout_while_processing_request_exists(): void
    {
        $user = User::factory()->create(['referral_points' => 5000]);
        PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'source' => 'referral_milestone',
            'status' => 'processing',
            'amount' => 100,
        ]);

        $this->assertFalse($user->canRequestReferralPayout());
    }
}
