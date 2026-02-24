<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\User;
use App\Services\EarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningServiceTest extends TestCase
{
    use RefreshDatabase;

    private EarningService $earningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->earningService = new EarningService;
    }

    public function test_approves_earning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $influencer = User::factory()->create(['role' => 'influencer']);

        $earning = Earning::factory()->create([
            'user_id' => $influencer->id,
            'status' => Earning::STATUS_PENDING,
            'amount' => 100.00,
        ]);

        $approvedEarning = $this->earningService->approve($earning, $admin);

        $this->assertEquals(Earning::STATUS_APPROVED, $approvedEarning->status);
        $this->assertEquals($admin->id, $approvedEarning->approved_by);
        $this->assertNotNull($approvedEarning->approved_at);
    }

    public function test_only_admin_can_approve_earning(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $earning = Earning::factory()->create();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->earningService->approve($earning, $customer);
    }

    public function test_approves_multiple_earnings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $influencer = User::factory()->create(['role' => 'influencer']);

        $earnings = Earning::factory()->count(5)->create([
            'user_id' => $influencer->id,
            'status' => Earning::STATUS_PENDING,
        ]);

        $earningIds = $earnings->pluck('id')->toArray();
        $count = $this->earningService->approveMultiple($earningIds, $admin);

        $this->assertEquals(5, $count);
        $this->assertEquals(5, Earning::where('status', '=', Earning::STATUS_APPROVED)->count());
    }

    public function test_marks_earning_as_paid(): void
    {
        $earning = Earning::factory()->create([
            'status' => Earning::STATUS_APPROVED,
            'amount' => 100.00,
        ]);

        $paidEarning = $this->earningService->markAsPaid($earning);

        $this->assertEquals(Earning::STATUS_PAID, $paidEarning->status);
        $this->assertNotNull($paidEarning->paid_at);
    }

    public function test_gets_user_earnings_summary(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Earning::STATUS_PENDING,
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 200.00,
            'status' => Earning::STATUS_APPROVED,
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => Earning::STATUS_PAID,
        ]);

        $summary = $this->earningService->getUserEarningsSummary($user);

        $this->assertEquals(350.00, $summary['total_earnings']);
        $this->assertEquals(100.00, $summary['pending_earnings']);
        $this->assertEquals(200.00, $summary['approved_earnings']);
        $this->assertEquals(50.00, $summary['paid_earnings']);
    }

    public function test_gets_available_for_payout(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Earning::STATUS_APPROVED,
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => Earning::STATUS_APPROVED,
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 200.00,
            'status' => Earning::STATUS_PENDING,
        ]);

        $available = $this->earningService->getAvailableForPayout($user);

        $this->assertEquals(150.00, $available);
    }

    public function test_gets_earnings_for_period(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'earned_at' => now()->subDays(10),
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'earned_at' => now()->subDays(5),
        ]);
        Earning::factory()->create([
            'user_id' => $user->id,
            'earned_at' => now()->subDays(20),
        ]);

        $startDate = now()->subDays(15)->startOfDay();
        $endDate = now()->endOfDay();

        $earnings = $this->earningService->getEarningsForPeriod($user, $startDate, $endDate);

        $this->assertEquals(2, $earnings->count());
    }
}
