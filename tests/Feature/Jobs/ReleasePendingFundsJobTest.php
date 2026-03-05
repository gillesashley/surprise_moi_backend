<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ReleasePendingFundsJob;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePendingFundsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_releases_funds_for_delivered_orders_past_cooling_period(): void
    {
        $vendor = User::factory()->vendor()->create();
        $balance = VendorBalance::factory()->create([
            'vendor_id' => $vendor->id,
            'pending_balance' => 500.00,
            'available_balance' => 0,
        ]);

        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
            'delivered_at' => now()->subHours(25),
            'funds_release_at' => now()->subHours(1),
            'funds_released' => false,
            'vendor_payout_amount' => 500.00,
            'payment_status' => 'paid',
        ]);

        $job = new ReleasePendingFundsJob;
        $job->handle(app(\App\Services\VendorBalanceService::class));

        $order->refresh();
        $this->assertTrue($order->funds_released);

        $balance->refresh();
        $this->assertEquals(0, (float) $balance->pending_balance);
        $this->assertEquals(500.00, (float) $balance->available_balance);
    }

    public function test_does_not_release_funds_within_cooling_period(): void
    {
        $vendor = User::factory()->vendor()->create();
        $balance = VendorBalance::factory()->create([
            'vendor_id' => $vendor->id,
            'pending_balance' => 500.00,
            'available_balance' => 0,
        ]);

        Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
            'delivered_at' => now()->subHours(12),
            'funds_release_at' => now()->addHours(12),
            'funds_released' => false,
            'vendor_payout_amount' => 500.00,
            'payment_status' => 'paid',
        ]);

        $job = new ReleasePendingFundsJob;
        $job->handle(app(\App\Services\VendorBalanceService::class));

        $balance->refresh();
        $this->assertEquals(500.00, (float) $balance->pending_balance);
        $this->assertEquals(0, (float) $balance->available_balance);
    }

    public function test_skips_already_released_orders(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorBalance::factory()->create([
            'vendor_id' => $vendor->id,
            'pending_balance' => 0,
            'available_balance' => 500.00,
        ]);

        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2),
            'funds_release_at' => now()->subDays(1),
            'funds_released' => true,
            'vendor_payout_amount' => 500.00,
            'payment_status' => 'paid',
        ]);

        $job = new ReleasePendingFundsJob;
        $job->handle(app(\App\Services\VendorBalanceService::class));

        $order->refresh();
        $this->assertTrue($order->funds_released);
    }
}
