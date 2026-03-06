<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $vendor;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->vendor = User::factory()->create(['role' => 'vendor']);
        $this->address = Address::factory()->create(['user_id' => $this->customer->id]);
    }

    public function test_customer_can_create_order_with_product(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'is_available' => true,
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id',
                    'order_number',
                    'subtotal',
                    'total',
                    'status',
                    'items',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
        ]);

        // Check stock was decremented
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_customer_can_create_order_with_service(): void
    {
        $service = Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'charge_start' => 500.00,
            'availability' => 'available',
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'service',
                        'orderable_id' => $service->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
                'scheduled_datetime' => now()->addDays(3)->toDateTimeString(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $this->assertDatabaseHas('order_items', [
            'orderable_type' => Service::class,
            'orderable_id' => $service->id,
        ]);
    }

    public function test_cannot_order_unavailable_product(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Failed to create order: Product "'.$product->name.'" is not available.']);
    }

    public function test_cannot_order_more_than_available_stock(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 10,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Failed to create order: Insufficient stock for "'.$product->name.'".']);
    }

    public function test_cannot_order_unavailable_service(): void
    {
        $service = Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'availability' => 'booked',
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'service',
                        'orderable_id' => $service->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_order_from_multiple_vendors(): void
    {
        $vendor2 = User::factory()->create(['role' => 'vendor']);

        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $vendor2->id,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product1->id,
                        'quantity' => 1,
                    ],
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product2->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Failed to create order: Cannot order items from multiple vendors in a single order.']);
    }

    public function test_order_applies_coupon_discount(): void
    {
        $coupon = Coupon::factory()->active()->create([
            'type' => 'percentage',
            'value' => 20,
            'min_purchase_amount' => 50,
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
                'coupon_code' => $coupon->code,
            ]);

        $response->assertStatus(201);

        $responseData = $response->json();
        $order = Order::find($responseData['order']['id']);
        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(20.00, (float) $order->discount_amount);
        $this->assertEquals(0, (float) $order->delivery_fee);
        $this->assertEquals(80.00, (float) $order->total);

        // Check coupon usage was recorded
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $this->customer->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_customer_can_view_their_orders(): void
    {
        Order::factory()->count(3)->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        // Create order for another customer
        Order::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_customer_can_filter_orders_by_status(): void
    {
        Order::factory()->pending()->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        Order::factory()->confirmed()->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/orders?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_customer_can_view_single_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'order_number',
                    'subtotal',
                    'total',
                    'status',
                ],
            ]);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create([
            'user_id' => $otherCustomer->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(403);
    }

    public function test_customer_can_track_order(): void
    {
        $order = Order::factory()->confirmed()->create([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'tracking_number' => 'TRK-12345678',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/orders/{$order->id}/track");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order_number',
                'status',
                'tracking_number',
                'confirmed_at',
            ]);
    }

    public function test_vendor_can_view_their_orders(): void
    {
        Order::factory()->count(2)->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => User::factory(),
        ]);

        // Create order for another vendor
        Order::factory()->create(['vendor_id' => User::factory()->create(['role' => 'vendor'])]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_vendor_can_update_order_status(): void
    {
        $order = Order::factory()->pending()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => User::factory(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Order status updated successfully.']);

        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);
    }

    public function test_vendor_can_view_order_statistics(): void
    {
        Order::factory()->count(3)->pending()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => User::factory(),
        ]);

        Order::factory()->count(2)->delivered()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => User::factory(),
            'total' => 100,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/orders/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'pending_orders',
                    'delivered_orders',
                    'total_revenue',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['total_orders']);
        $this->assertEquals(3, $data['pending_orders']);
        $this->assertEquals(2, $data['delivered_orders']);
    }

    public function test_customer_cannot_view_order_statistics(): void
    {
        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/orders/statistics');

        $response->assertStatus(403);
    }

    public function test_order_requires_items(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_order_requires_delivery_address(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_address_id']);
    }

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $response = $this->postJson('/api/v1/orders', []);

        $response->assertStatus(401);
    }

    public function test_order_with_special_instructions(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
                'special_instructions' => 'Please leave at door',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'special_instructions' => 'Please leave at door',
        ]);
    }

    public function test_multi_item_order_creates_one_order_with_distinct_items(): void
    {
        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'White Love',
            'price' => 3.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kay Hampers for big boys',
            'price' => 1.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 5,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product1->id,
                        'quantity' => 1,
                    ],
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product2->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(201);

        // Exactly ONE order should be created
        $this->assertDatabaseCount('orders', 1);

        // TWO distinct order items
        $this->assertDatabaseCount('order_items', 2);

        $order = Order::first();
        $this->assertEquals(4.00, (float) $order->total);

        // Verify each item references the correct product
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'orderable_id' => $product1->id,
            'unit_price' => 3.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'orderable_id' => $product2->id,
            'unit_price' => 1.00,
        ]);

        // Verify the API response includes both items with correct names
        $response->assertJsonCount(2, 'order.items');

        $items = collect($response->json('order.items'));
        $this->assertTrue($items->contains('orderable.name', 'White Love'));
        $this->assertTrue($items->contains('orderable.name', 'Kay Hampers for big boys'));
    }

    public function test_multi_item_order_get_returns_correct_products(): void
    {
        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Product Alpha',
            'price' => 10.00,
            'is_available' => true,
            'stock' => 5,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Product Beta',
            'price' => 20.00,
            'is_available' => true,
            'stock' => 5,
        ]);

        // Create the order via API
        $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product1->id,
                        'quantity' => 1,
                    ],
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product2->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ])
            ->assertStatus(201);

        // Now fetch via GET /orders and verify each item has the correct product
        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/orders');

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);

        $items = collect($orders[0]['items']);
        $this->assertCount(2, $items);

        // Each item must reference a DIFFERENT product with correct name
        $names = $items->pluck('orderable.name')->sort()->values();
        $this->assertEquals(['Product Alpha', 'Product Beta'], $names->toArray());
    }

    public function test_concurrent_orders_for_last_item_only_one_succeeds(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 50.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 1,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $customer2 = User::factory()->create(['role' => 'customer']);
        $address2 = Address::factory()->create(['user_id' => $customer2->id]);

        $payload1 = [
            'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 1]],
            'delivery_address_id' => $this->address->id,
        ];

        $payload2 = [
            'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 1]],
            'delivery_address_id' => $address2->id,
        ];

        $response1 = $this->actingAs($this->customer)->postJson('/api/v1/orders', $payload1);
        $response2 = $this->actingAs($customer2)->postJson('/api/v1/orders', $payload2);

        $statuses = [$response1->status(), $response2->status()];
        sort($statuses);

        $this->assertEquals([201, 422], $statuses);
        $this->assertEquals(0, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_multi_item_order_restores_stock_when_second_item_unavailable(): void
    {
        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 10.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 5,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 20.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 0,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['orderable_type' => 'product', 'orderable_id' => $product1->id, 'quantity' => 2],
                    ['orderable_type' => 'product', 'orderable_id' => $product2->id, 'quantity' => 1],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422);
        $this->assertEquals(5, $product1->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stock_restored_on_order_failure_after_reservation(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 30.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 3]],
                'delivery_address_id' => $this->address->id,
                'coupon_code' => 'INVALID_COUPON_DOES_NOT_EXIST',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_order_with_scheduled_datetime(): void
    {
        $service = Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'availability' => 'available',
        ]);

        $scheduledDate = now()->addDays(5);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'service',
                        'orderable_id' => $service->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
                'scheduled_datetime' => $scheduledDate->toDateTimeString(),
            ]);

        $response->assertStatus(201);

        $order = Order::first();
        $this->assertNotNull($order->scheduled_datetime);
    }

    public function test_order_uses_cart_prices_not_current_product_prices(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 8.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        // Simulate: user added product to cart when price was 8.00
        $cart = \App\Models\Cart::create(['user_id' => $this->customer->id, 'currency' => 'GHS']);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $product->name,
            'unit_price_cents' => 800, // GH₵8.00
            'quantity' => 1,
        ]);
        $cart->recalculateTotals();
        $cart->save();

        // Vendor updates price to 58.00 after user added to cart
        $product->update(['price' => 58.00]);

        // Create order — should use cart price (8.00), not current price (58.00)
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(201);

        $order = Order::first();
        $this->assertEquals(8.00, (float) $order->subtotal, 'Order should use cart price (8.00), not current product price (58.00)');
        $this->assertEquals(8.00, (float) $order->total);
    }

    public function test_order_rejects_when_product_price_changed(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 8.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        // Cart has old price
        $cart = \App\Models\Cart::create(['user_id' => $this->customer->id, 'currency' => 'GHS']);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $product->name,
            'unit_price_cents' => 800,
            'quantity' => 1,
        ]);
        $cart->recalculateTotals();
        $cart->save();

        // Price decreased — cart is stale
        $product->update(['price' => 2.00]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonFragment(['code' => 'price_changed']);
    }

    public function test_cart_is_cleared_after_successful_order_creation(): void
    {
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'price' => 10.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        // Add item to cart
        $cart = \App\Models\Cart::create(['user_id' => $this->customer->id, 'currency' => 'GHS']);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $product->name,
            'unit_price_cents' => 1000,
            'quantity' => 2,
        ]);
        $cart->recalculateTotals();
        $cart->save();

        $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'orderable_type' => 'product',
                        'orderable_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
                'delivery_address_id' => $this->address->id,
            ])
            ->assertStatus(201);

        // Cart should be empty after order
        $this->assertEquals(0, $cart->fresh()->items()->count());
        $this->assertEquals(0, $cart->fresh()->total_cents);
    }
}
