<?php

namespace Tests\Feature;

use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\RiderWithdrawalRequest;
use App\Services\RiderBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RiderBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RiderBalanceService;
    }

    public function test_credit_earning_creates_pending_earning(): void
    {
        $rider = Rider::factory()->approved()->create();
        $order = Order::factory()->create(['vendor_id' => null]);
        $deliveryRequest = DeliveryRequest::factory()->create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'delivery_fee' => 25.00,
        ]);

        $earning = $this->service->creditEarning($rider, $deliveryRequest);

        $this->assertInstanceOf(RiderEarning::class, $earning);
        $this->assertDatabaseHas('rider_earnings', [
            'rider_id' => $rider->id,
            'order_id' => $order->id,
            'delivery_request_id' => $deliveryRequest->id,
            'type' => 'delivery_fee',
            'status' => 'pending',
        ]);
        $this->assertEquals(25.00, (float) $earning->amount);
    }

    public function test_release_pending_earnings_updates_eligible_records(): void
    {
        $rider = Rider::factory()->approved()->create();

        // Earning that should be released (past hold period)
        RiderEarning::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'pending',
            'available_at' => now()->subHour(),
        ]);

        // Earning that should NOT be released (still in hold period)
        RiderEarning::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'pending',
            'available_at' => now()->addHours(23),
        ]);

        $released = $this->service->releasePendingEarnings();

        $this->assertEquals(1, $released);
        $this->assertDatabaseHas('rider_earnings', [
            'rider_id' => $rider->id,
            'status' => 'available',
        ]);
    }

    public function test_get_balance_summary_returns_correct_values(): void
    {
        $rider = Rider::factory()->approved()->create();

        RiderEarning::factory()->available()->create([
            'rider_id' => $rider->id,
            'amount' => 50.00,
        ]);
        RiderEarning::factory()->available()->create([
            'rider_id' => $rider->id,
            'amount' => 30.00,
        ]);
        RiderEarning::factory()->create([
            'rider_id' => $rider->id,
            'amount' => 20.00,
            'status' => 'pending',
        ]);

        RiderWithdrawalRequest::factory()->completed()->create([
            'rider_id' => $rider->id,
            'amount' => 15.00,
        ]);

        $summary = $this->service->getBalanceSummary($rider);

        $this->assertEquals(80.00, $summary['available']);
        $this->assertEquals(20.00, $summary['pending']);
        $this->assertEquals(100.00, $summary['total_earned']);
        $this->assertEquals(15.00, $summary['total_withdrawn']);
    }

    public function test_process_withdrawal_succeeds_with_sufficient_balance(): void
    {
        $rider = Rider::factory()->approved()->create();

        RiderEarning::factory()->available()->create([
            'rider_id' => $rider->id,
            'amount' => 100.00,
        ]);

        $withdrawal = $this->service->processWithdrawal($rider, 50.00, 'mtn', '0241234567');

        $this->assertInstanceOf(RiderWithdrawalRequest::class, $withdrawal);
        $this->assertDatabaseHas('rider_withdrawal_requests', [
            'rider_id' => $rider->id,
            'amount' => 50.00,
            'mobile_money_provider' => 'mtn',
            'mobile_money_number' => '0241234567',
            'status' => 'pending',
        ]);
    }

    public function test_process_withdrawal_returns_null_with_insufficient_balance(): void
    {
        $rider = Rider::factory()->approved()->create();

        RiderEarning::factory()->available()->create([
            'rider_id' => $rider->id,
            'amount' => 30.00,
        ]);

        $withdrawal = $this->service->processWithdrawal($rider, 50.00, 'mtn', '0241234567');

        $this->assertNull($withdrawal);
        $this->assertDatabaseMissing('rider_withdrawal_requests', [
            'rider_id' => $rider->id,
        ]);
    }

    public function test_process_withdrawal_returns_null_when_no_earnings(): void
    {
        $rider = Rider::factory()->approved()->create();

        $withdrawal = $this->service->processWithdrawal($rider, 10.00, 'vodafone', '0201234567');

        $this->assertNull($withdrawal);
    }

    public function test_get_balance_summary_returns_zeros_for_new_rider(): void
    {
        $rider = Rider::factory()->approved()->create();

        $summary = $this->service->getBalanceSummary($rider);

        $this->assertEquals(0.0, $summary['available']);
        $this->assertEquals(0.0, $summary['pending']);
        $this->assertEquals(0.0, $summary['total_earned']);
        $this->assertEquals(0.0, $summary['total_withdrawn']);
    }
}
