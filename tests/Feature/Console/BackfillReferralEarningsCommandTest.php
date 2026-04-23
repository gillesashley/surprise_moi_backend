<?php

namespace Tests\Feature\Console;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillReferralEarningsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_earning_for_active_referral_without_existing_earning(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);
        $referral = Referral::factory()->active()->create([
            'influencer_id' => $agent->id,
            'earned_amount' => 0.94,
        ]);

        $this->artisan('earnings:backfill-referrals')->assertSuccessful();

        $earning = Earning::where('earnable_type', Referral::class)
            ->where('earnable_id', $referral->id)
            ->firstOrFail();

        $this->assertSame($agent->id, $earning->user_id);
        $this->assertSame('field_agent', $earning->user_role);
        $this->assertSame(Earning::TYPE_REFERRAL_BONUS, $earning->earning_type);
        $this->assertSame(Earning::STATUS_PENDING, $earning->status);
        $this->assertSame('0.94', (string) $earning->amount);
    }

    public function test_skips_when_earning_already_exists(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);
        $referral = Referral::factory()->active()->create([
            'influencer_id' => $agent->id,
            'earned_amount' => 5.00,
        ]);
        Earning::factory()->create([
            'user_id' => $agent->id,
            'user_role' => 'field_agent',
            'earnable_type' => Referral::class,
            'earnable_id' => $referral->id,
            'amount' => 5.00,
            'status' => Earning::STATUS_PENDING,
        ]);

        $this->artisan('earnings:backfill-referrals')->assertSuccessful();

        $this->assertSame(1, Earning::where('earnable_id', $referral->id)->count());
    }

    public function test_skips_users_that_are_not_earning_capable(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Referral::factory()->active()->create([
            'influencer_id' => $customer->id,
            'earned_amount' => 2.00,
        ]);

        $this->artisan('earnings:backfill-referrals')->assertSuccessful();

        $this->assertSame(0, Earning::count());
    }

    public function test_skips_pending_referrals(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);
        Referral::factory()->create([
            'influencer_id' => $agent->id,
            'status' => Referral::STATUS_PENDING,
            'earned_amount' => 0.94,
        ]);

        $this->artisan('earnings:backfill-referrals')->assertSuccessful();

        $this->assertSame(0, Earning::count());
    }

    public function test_dry_run_creates_nothing(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);
        Referral::factory()->active()->create([
            'influencer_id' => $agent->id,
            'earned_amount' => 0.94,
        ]);

        $this->artisan('earnings:backfill-referrals', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Earning::count());
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);
        Referral::factory()->active()->create([
            'influencer_id' => $agent->id,
            'earned_amount' => 0.94,
        ]);

        $this->artisan('earnings:backfill-referrals');
        $this->artisan('earnings:backfill-referrals');

        $this->assertSame(1, Earning::count());
    }
}
