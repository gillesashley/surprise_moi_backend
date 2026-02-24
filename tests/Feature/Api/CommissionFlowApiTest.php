<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionFlowApiTest extends TestCase
{
    use RefreshDatabase;

    private string $vendorToken;

    private string $customerToken;

    private User $vendor;

    private User $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    private function setupTestData(): void
    {
        // Create a Tier 1 vendor (registered business)
        $this->vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $this->vendorToken = $this->vendor->createToken('vendor-test')->plainTextToken;

        // Create a customer
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->customerToken = $this->customer->createToken('customer-test')->plainTextToken;

        // Create a product for the vendor
        $this->product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'is_available' => true,
        ]);

        // Create delivery address for customer
        Address::factory()->create(['user_id' => $this->customer->id]);
    }

    /**
     * Test creating an order through the API.
     */
    public function test_create_order_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->customer->addresses()->first()->id,
                'coupon_code' => null,
            ]);

        $response->assertStatus(201);
        $this->assertTrue(Order::count() > 0);
        $this->assertEquals('Order created successfully.', $response->json('message'));
    }

    /**
     * Test getting orders list.
     */
    public function test_get_orders_list_api(): void
    {
        // Create some orders first
        Order::factory()->count(3)->create(['user_id' => $this->customer->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(3, count($data));
    }

    /**
     * Test getting a specific order with commission details.
     */
    public function test_get_order_with_commission_details(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'total' => 100.00,
            'platform_commission_rate' => 12.00,
            'platform_commission_amount' => 12.00,
            'vendor_payout_amount' => 88.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertEquals(100.00, $response->json('order.total'));
        $this->assertEquals(12.00, $response->json('order.platform_commission_rate'));
        $this->assertEquals(12.00, $response->json('order.platform_commission_amount'));
        $this->assertEquals(88.00, $response->json('order.vendor_payout_amount'));
    }

    /**
     * Test updating order status.
     */
    public function test_update_order_status_api(): void
    {
        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->vendorToken}")
            ->postJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Order status updated successfully.', $response->json('message'));
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    /**
     * Test tracking an order.
     */
    public function test_track_order_api(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'tracking_number' => 'TRACK123456',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->getJson("/api/v1/orders/{$order->id}/track");

        $response->assertStatus(200);
        $this->assertEquals('TRACK123456', $response->json('tracking_number'));
        $this->assertEquals($order->order_number, $response->json('order_number'));
    }

    /**
     * Test getting vendor statistics.
     */
    public function test_vendor_statistics_api(): void
    {
        // Create various orders for the vendor
        Order::factory()->create(['vendor_id' => $this->vendor->id, 'status' => 'pending']);
        Order::factory()->create(['vendor_id' => $this->vendor->id, 'status' => 'confirmed']);
        Order::factory()->create(['vendor_id' => $this->vendor->id, 'status' => 'delivered']);

        $response = $this->withHeader('Authorization', "Bearer {$this->vendorToken}")
            ->getJson('/api/v1/orders/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data');
        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_orders']);
        $this->assertEquals(1, $stats['pending_orders']);
        $this->assertEquals(1, $stats['confirmed_orders']);
        $this->assertEquals(1, $stats['delivered_orders']);
    }

    /**
     * Test unauthorized access to other user's orders.
     */
    public function test_unauthorized_order_access(): void
    {
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create(['user_id' => $otherCustomer->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(403);
    }

    /**
     * Test vendor cannot update other vendor's order.
     */
    public function test_vendor_cannot_update_other_order(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);
        $order = Order::factory()->create(['vendor_id' => $otherVendor->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->vendorToken}")
            ->postJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(403);
    }
}
