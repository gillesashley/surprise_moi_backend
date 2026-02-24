<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\VendorBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected VendorBalanceService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balanceService = app(VendorBalanceService::class);
    }

    /**
     * Test that vendor tier is correctly set when vendor application is approved.
     */
    public function test_vendor_tier_set_on_application_approval(): void
    {
        // Create a user and vendor application with business certificate
        $user = User::factory()->create(['role' => 'customer']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $application = VendorApplication::factory()->create([
            'user_id' => $user->id,
            'has_business_certificate' => true,
            'status' => 'pending',
        ]);

        // Approve the application
        $application->approve($reviewer->id);

        // Verify user is now vendor with Tier 1
        $this->assertEquals('vendor', $user->fresh()->role);
        $this->assertEquals(1, $user->fresh()->vendor_tier);
    }

    /**
     * Test that individual vendor (no certificate) gets Tier 2.
     */
    public function test_individual_vendor_gets_tier_two(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $application = VendorApplication::factory()->create([
            'user_id' => $user->id,
            'has_business_certificate' => false,
            'status' => 'pending',
        ]);

        $application->approve($reviewer->id);

        $this->assertEquals('vendor', $user->fresh()->role);
        $this->assertEquals(2, $user->fresh()->vendor_tier);
    }

    /**
     * Test commission rate retrieval for Tier 1 vendor.
     */
    public function test_tier_one_vendor_commission_rate(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);

        $this->assertEquals(12.00, $vendor->getCommissionRate());
    }

    /**
     * Test commission rate retrieval for Tier 2 vendor.
     */
    public function test_tier_two_vendor_commission_rate(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 2]);

        $this->assertEquals(8.00, $vendor->getCommissionRate());
    }

    /**
     * Test non-vendor users have 0% commission rate.
     */
    public function test_non_vendor_commission_rate_zero(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->assertEquals(0, $customer->getCommissionRate());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertEquals(0, $admin->getCommissionRate());
    }

    /**
     * Test commission calculation when crediting pending balance for Tier 1 vendor.
     */
    public function test_commission_deduction_tier_one_vendor(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $customer = User::factory()->create(['role' => 'customer']);

        // Create product for vendor
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 100.00]);

        // Create order
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'payment_status' => 'pending',
        ]);

        // Create order item
        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        // Credit pending balance (simulating successful payment)
        $this->balanceService->creditPendingBalance($order);

        // Refresh order to get updated commission data
        $order = $order->fresh();

        // Verify commission was calculated correctly
        $this->assertEquals(12.00, $order->platform_commission_rate);
        $this->assertEquals(12.00, $order->platform_commission_amount);
        $this->assertEquals(88.00, $order->vendor_payout_amount);

        // Verify vendor balance was credited with net amount (88.00, not 100.00)
        $vendorBalance = $vendor->vendorBalance()->first();
        $this->assertEquals(88.00, $vendorBalance->pending_balance);
        $this->assertEquals(88.00, $vendorBalance->total_earned);
    }

    /**
     * Test commission calculation for Tier 2 vendor.
     */
    public function test_commission_deduction_tier_two_vendor(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 2]);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 100.00]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        $this->balanceService->creditPendingBalance($order);

        $order = $order->fresh();

        // Tier 2 should have 8% commission
        $this->assertEquals(8.00, $order->platform_commission_rate);
        $this->assertEquals(8.00, $order->platform_commission_amount);
        $this->assertEquals(92.00, $order->vendor_payout_amount);

        $vendorBalance = $vendor->vendorBalance()->first();
        $this->assertEquals(92.00, $vendorBalance->pending_balance);
    }

    /**
     * Test that release funds uses vendor payout amount, not total.
     */
    public function test_release_funds_uses_vendor_payout_amount(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 100.00]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        // Credit pending balance first
        $this->balanceService->creditPendingBalance($order);

        // Release funds (move from pending to available)
        $this->balanceService->releaseFunds($order);

        $vendorBalance = $vendor->vendorBalance()->first();

        // Pending should be 0, available should be 88.00 (not 100.00)
        $this->assertEquals(0, $vendorBalance->pending_balance);
        $this->assertEquals(88.00, $vendorBalance->available_balance);
    }

    /**
     * Test refund uses vendor payout amount.
     */
    public function test_refund_uses_vendor_payout_amount(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 100.00]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'payment_status' => 'paid',
            'platform_commission_rate' => 12.00,
            'platform_commission_amount' => 12.00,
            'vendor_payout_amount' => 88.00,
        ]);

        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        // First credit the balance
        $this->balanceService->creditPendingBalance($order);

        // Then refund it
        $this->balanceService->refundOrder($order, 'Customer requested refund');

        $vendorBalance = $vendor->vendorBalance()->first();

        // Balance should be back to 0 (refunded 88.00)
        $this->assertEquals(0, $vendorBalance->pending_balance);
        $this->assertEquals(0, $vendorBalance->available_balance);
    }

    /**
     * Test commission calculation with decimal amounts.
     */
    public function test_commission_calculation_with_decimal_amounts(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 99.99]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 99.99,
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 99.99,
            'subtotal' => 99.99,
        ]);

        $this->balanceService->creditPendingBalance($order);

        $order = $order->fresh();

        // 12% of 99.99 = 11.9988 -> should round to 12.00
        // 99.99 - 11.99 = 88.00
        $this->assertEquals(12.00, $order->platform_commission_rate);
        $expectedCommission = round((99.99 * 12.00) / 100, 2);
        $expectedPayout = 99.99 - $expectedCommission;
        $this->assertEqualsWithDelta($expectedCommission, $order->platform_commission_amount, 0.01);
        $this->assertEqualsWithDelta($expectedPayout, $order->vendor_payout_amount, 0.01);
    }

    /**
     * Test transaction metadata includes commission breakdown.
     */
    public function test_transaction_metadata_includes_commission(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 100.00]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 100.00,
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        $this->balanceService->creditPendingBalance($order);

        $vendorBalance = $vendor->vendorBalance()->first();
        $transaction = $vendorBalance->transactions()->first();

        // Verify transaction has commission metadata
        $this->assertNotNull($transaction);
        $metadata = $transaction->metadata;
        $this->assertEquals(100.00, $metadata['order_total']);
        $this->assertEquals(12.00, $metadata['commission_rate']);
        $this->assertEquals(12.00, $metadata['commission_amount']);
        $this->assertEquals(88.00, $metadata['vendor_payout_amount']);
    }
}
