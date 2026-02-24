<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected User $customer;

    protected User $admin;

    protected Category $category1;

    protected Category $category2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->vendor()->create();
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category1 = Category::factory()->create(['name' => 'Gift Boxes']);
        $this->category2 = Category::factory()->create(['name' => 'Flowers']);
    }

    // ==================== Authorization Tests ====================

    public function test_customer_cannot_access_analytics(): void
    {
        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Vendor access required.']);
    }

    public function test_vendor_can_access_their_analytics(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overview',
                    'revenue_by_category',
                    'top_products',
                    'orders_trend',
                    'revenue_trend',
                    'period',
                    'date_range',
                ],
            ]);
    }

    public function test_admin_can_access_analytics(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $response = $this->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(401);
    }

    // ==================== Overview Stats Tests ====================

    public function test_overview_endpoint_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'monthly_target' => ['target', 'achieved', 'progress_percentage', 'currency'],
                    'today' => ['revenue', 'orders', 'currency'],
                    'period' => ['revenue', 'orders', 'completed_orders', 'average_order_value', 'currency'],
                    'changes' => ['revenue_percentage', 'orders_percentage'],
                    'order_status_breakdown',
                ],
            ]);
    }

    public function test_overview_calculates_revenue_correctly(): void
    {
        // Create completed orders for this month
        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
            'price' => 100.00,
        ]);

        $order1 = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 150.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $order2 = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 250.00,
            'status' => 'fulfilled',
            'created_at' => Carbon::now(),
        ]);

        // Create pending order (should not count in revenue)
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 100.00,
            'status' => 'pending',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/overview');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Total revenue should be 150 + 250 = 400
        $this->assertEquals(400.00, $data['period']['revenue']);
        $this->assertEquals(3, $data['period']['orders']); // All orders
        $this->assertEquals(2, $data['period']['completed_orders']); // Only delivered/fulfilled
        $this->assertEquals(200.00, $data['period']['average_order_value']); // 400 / 2
    }

    public function test_overview_calculates_today_stats_correctly(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 500.00,
            'status' => 'delivered',
            'created_at' => Carbon::today()->addHours(2),
        ]);

        // Yesterday's order (should not count)
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 300.00,
            'status' => 'delivered',
            'created_at' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/overview');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(500.00, $data['today']['revenue']);
        $this->assertEquals(1, $data['today']['orders']);
    }

    public function test_order_status_breakdown_is_correct(): void
    {
        // Create orders with different statuses
        Order::factory()->count(2)->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->count(3)->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'confirmed',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/overview');

        $response->assertStatus(200);

        $breakdown = $response->json('data.order_status_breakdown');

        $this->assertEquals(2, $breakdown['pending']);
        $this->assertEquals(3, $breakdown['confirmed']);
        $this->assertEquals(1, $breakdown['delivered']);
    }

    // ==================== Revenue by Category Tests ====================

    public function test_revenue_by_category_endpoint(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/revenue-by-category');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_revenue_by_category_calculates_correctly(): void
    {
        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
            'price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category2->id,
            'price' => 50.00,
        ]);

        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 250.00,
            'created_at' => Carbon::now(),
        ]);

        // Gift Boxes: 2 items @ 100 = 200
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
        ]);

        // Flowers: 1 item @ 50 = 50
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 50.00,
            'subtotal' => 50.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/revenue-by-category');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(2, $data);

        // Find Gift Boxes category
        $giftBoxes = collect($data)->firstWhere('name', 'Gift Boxes');
        $this->assertEquals(200.00, $giftBoxes['revenue']);
        $this->assertEquals(2, $giftBoxes['quantity']);

        // Find Flowers category
        $flowers = collect($data)->firstWhere('name', 'Flowers');
        $this->assertEquals(50.00, $flowers['revenue']);
        $this->assertEquals(1, $flowers['quantity']);
    }

    // ==================== Top Products Tests ====================

    public function test_top_products_endpoint(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/top-products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_top_products_returns_correct_data(): void
    {
        $product1 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
            'name' => 'Luxury Gift Box',
            'price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category2->id,
            'name' => 'Rose Bouquet',
            'price' => 50.00,
        ]);

        // Order 1: Product 1 with more revenue
        $order1 = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product1->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'subtotal' => 500.00,
        ]);

        // Order 2: Product 2 with less revenue
        $order2 = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 50.00,
            'subtotal' => 150.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/top-products');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(2, $data);

        // First should be Luxury Gift Box (highest revenue)
        $this->assertEquals('Luxury Gift Box', $data[0]['name']);
        $this->assertEquals(500.00, $data[0]['revenue']);
        $this->assertEquals(5, $data[0]['orders']);

        // Second should be Rose Bouquet
        $this->assertEquals('Rose Bouquet', $data[1]['name']);
        $this->assertEquals(150.00, $data[1]['revenue']);
    }

    public function test_top_products_respects_limit(): void
    {
        // Create 5 products with orders
        for ($i = 1; $i <= 5; $i++) {
            $product = Product::factory()->create([
                'vendor_id' => $this->vendor->id,
                'category_id' => $this->category1->id,
                'name' => "Product {$i}",
            ]);

            $order = Order::factory()->create([
                'vendor_id' => $this->vendor->id,
                'status' => 'delivered',
                'created_at' => Carbon::now(),
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'orderable_type' => Product::class,
                'orderable_id' => $product->id,
                'quantity' => $i,
                'subtotal' => $i * 100,
            ]);
        }

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/top-products?limit=3');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(3, $data);
    }

    // ==================== Trends Tests ====================

    public function test_trends_endpoint(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics/trends');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders',
                    'revenue',
                ],
            ]);
    }

    // ==================== Period Filter Tests ====================

    public function test_analytics_filters_by_period(): void
    {
        // Create order this month
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'delivered',
            'total' => 100.00,
            'created_at' => Carbon::now(),
        ]);

        // Create order last month (should not appear in month period)
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'delivered',
            'total' => 200.00,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $data = $response->json('data.overview.period');

        $this->assertEquals(100.00, $data['revenue']);
        $this->assertEquals(1, $data['completed_orders']);
    }

    public function test_analytics_supports_custom_date_range(): void
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->actingAs($this->vendor)
            ->getJson("/api/v1/vendor/analytics?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('data.date_range.start', Carbon::parse($startDate)->startOfDay()->toDateTimeString());
    }

    public function test_analytics_supports_week_period(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=week');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'week');
    }

    public function test_analytics_supports_year_period(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=year');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'year');
    }

    // ==================== Admin Access Tests ====================

    public function test_admin_can_view_specific_vendor_analytics(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'delivered',
            'total' => 500.00,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/vendor/analytics?vendor_id={$this->vendor->id}");

        $response->assertStatus(200);

        $data = $response->json('data.overview.period');

        $this->assertEquals(500.00, $data['revenue']);
    }

    // ==================== Vendor Isolation Tests ====================

    public function test_vendor_only_sees_own_analytics(): void
    {
        $otherVendor = User::factory()->vendor()->create();

        // Create order for current vendor
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => 'delivered',
            'total' => 100.00,
            'created_at' => Carbon::now(),
        ]);

        // Create order for other vendor
        Order::factory()->create([
            'vendor_id' => $otherVendor->id,
            'status' => 'delivered',
            'total' => 500.00,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200);

        $data = $response->json('data.overview.period');

        // Should only see own revenue
        $this->assertEquals(100.00, $data['revenue']);
        $this->assertEquals(1, $data['completed_orders']);
    }
}
