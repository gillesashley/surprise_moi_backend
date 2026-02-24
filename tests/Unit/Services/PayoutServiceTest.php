<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $payoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payoutService = new PayoutService;
    }

    public function test_creates_payout_request(): void
    {
        $user = User::factory()->create(['role' => 'influencer']);

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 500.00,
            'status' => Earning::STATUS_APPROVED,
        ]);

        $payoutRequest = $this->payoutService->createPayoutRequest(
            user: $user,
            amount: 100.00,
            payoutMethod: 'mobile_money',
            mobileMoneyNumber: '0244123456',
            mobileMoneyProvider: 'MTN'
        );

        $this->assertInstanceOf(PayoutRequest::class, $payoutRequest);
        $this->assertEquals($user->id, $payoutRequest->user_id);
        $this->assertEquals(100.00, $payoutRequest->amount);
        $this->assertEquals('mobile_money', $payoutRequest->payout_method);
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $payoutRequest->status);
    }

    public function test_throws_exception_when_amount_exceeds_available(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => Earning::STATUS_APPROVED,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->payoutService->createPayoutRequest(
            user: $user,
            amount: 100.00,
            payoutMethod: 'mobile_money',
            mobileMoneyNumber: '0244123456'
        );
    }

    public function test_throws_exception_when_amount_below_minimum(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Earning::STATUS_APPROVED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum payout amount is GHS 10');

        $this->payoutService->createPayoutRequest(
            user: $user,
            amount: 5.00,
            payoutMethod: 'mobile_money',
            mobileMoneyNumber: '0244123456'
        );
    }

    public function test_admin_approves_payout_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payoutRequest = PayoutRequest::factory()->create([
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $approved = $this->payoutService->approve($payoutRequest, $admin);

        $this->assertEquals(PayoutRequest::STATUS_APPROVED, $approved->status);
        $this->assertEquals($admin->id, $approved->processed_by);
    }

    public function test_only_admin_can_approve_payout(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $payoutRequest = PayoutRequest::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only admins can approve payout requests');

        $this->payoutService->approve($payoutRequest, $customer);
    }

    public function test_admin_rejects_payout_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payoutRequest = PayoutRequest::factory()->create([
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $rejected = $this->payoutService->reject($payoutRequest, $admin, 'Invalid account details');

        $this->assertEquals(PayoutRequest::STATUS_REJECTED, $rejected->status);
        $this->assertEquals('Invalid account details', $rejected->rejection_reason);
    }

    public function test_marks_payout_as_paid_and_updates_earnings(): void
    {
        $user = User::factory()->create();

        Earning::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Earning::STATUS_APPROVED,
        ]);

        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);

        $paid = $this->payoutService->markAsPaid($payoutRequest);

        $this->assertEquals(PayoutRequest::STATUS_PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);

        $earnings = Earning::where('user_id', $user->id)
            ->where('status', Earning::STATUS_PAID)
            ->get();
        $this->assertGreaterThan(0, $earnings->count());
    }
}
