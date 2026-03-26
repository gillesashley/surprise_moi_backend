<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOrdersDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_vendor_application_shows_vendor_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $application = VendorApplication::factory()
            ->for($vendor)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_APPROVED,
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        // Create orders for this vendor
        Order::factory()->count(3)->create([
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'total' => 100.00,
            'vendor_payout_amount' => 90.00,
        ]);

        Order::factory()->count(2)->create([
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/show')
            ->has('vendorOrders')
            ->has('vendorOrders.stats')
            ->has('vendorOrders.orders')
            ->where('vendorOrders.stats.total', 5)
            ->where('vendorOrders.stats.delivered', 3)
            ->where('vendorOrders.stats.pending', 2)
        );
    }

    public function test_pending_vendor_application_does_not_show_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/show')
            ->where('vendorOrders', null)
        );
    }

    public function test_approved_vendor_with_no_orders_returns_empty_stats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $application = VendorApplication::factory()
            ->for($vendor)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_APPROVED,
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/show')
            ->has('vendorOrders')
            ->where('vendorOrders.stats.total', 0)
            ->where('vendorOrders.orders.data', [])
        );
    }
}
