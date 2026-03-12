<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_statistics_only_counts_paid_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        // Paid order - should be counted
        Order::factory()->create([
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'platform_commission_amount' => 12.00,
            'payment_status' => 'paid',
        ]);

        // Pending order - should NOT be counted
        Order::factory()->create([
            'vendor_id' => $vendor->id,
            'total' => 200.00,
            'platform_commission_amount' => 24.00,
            'payment_status' => 'pending',
        ]);

        // Failed order - should NOT be counted
        Order::factory()->create([
            'vendor_id' => $vendor->id,
            'total' => 150.00,
            'platform_commission_amount' => 18.00,
            'payment_status' => 'failed',
        ]);

        // Unpaid order - should NOT be counted
        Order::factory()->create([
            'vendor_id' => $vendor->id,
            'total' => 50.00,
            'platform_commission_amount' => 6.00,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($admin)->get(route('commission-statistics'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('commission-statistics/index')
            ->where('stats.summary.total_orders', 1)
            ->where('stats.summary.total_order_value', '100.00')
            ->where('stats.summary.total_commission_earned', '12.00')
        );
    }

    public function test_guests_cannot_access_commission_statistics(): void
    {
        $this->get(route('commission-statistics'))->assertRedirect(route('login'));
    }
}
