<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
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

    // ==================== Index Endpoint Tests (Mobile Contract) ====================

    public function test_vendor_can_access_their_analytics(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'revenue' => ['total', 'previous_total', 'growth_percentage'],
                    'orders' => ['total', 'previous_total', 'growth_percentage'],
                    'revenue_by_category',
                    'top_products',
                ],
            ]);
    }

    public function test_index_defaults_to_month_period(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'month');
    }

    public function test_index_returns_correct_revenue_and_orders(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 150.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 250.00,
            'status' => 'fulfilled',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 100.00,
            'status' => 'pending',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 200.00,
            'status' => 'delivered',
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(400.00, $data['revenue']['total']);
        $this->assertEquals(200.00, $data['revenue']['previous_total']);
        $this->assertEquals(100.00, $data['revenue']['growth_percentage']);
        $this->assertEquals(2, $data['orders']['total']);
        $this->assertEquals(1, $data['orders']['previous_total']);
        $this->assertEquals(100.00, $data['orders']['growth_percentage']);
    }

    public function test_index_growth_percentage_zero_when_no_previous_data(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 500.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(500.00, $data['revenue']['total']);
        $this->assertEquals(0, $data['revenue']['previous_total']);
        $this->assertEquals(0, $data['revenue']['growth_percentage']);
    }

    public function test_index_returns_empty_state_correctly(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(0, $data['revenue']['total']);
        $this->assertEquals(0, $data['revenue']['previous_total']);
        $this->assertEquals(0, $data['revenue']['growth_percentage']);
        $this->assertEquals(0, $data['orders']['total']);
        $this->assertEmpty($data['revenue_by_category']);
        $this->assertEmpty($data['top_products']);
    }

    public function test_index_supports_today_period(): void
    {
        $this->travelTo(Carbon::today()->addHours(12));

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 100.00,
            'status' => 'delivered',
            'created_at' => Carbon::today()->addHours(2),
        ]);

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 80.00,
            'status' => 'delivered',
            'created_at' => Carbon::yesterday()->addHours(10),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=today');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'today');

        $data = $response->json('data');

        $this->assertEquals(100.00, $data['revenue']['total']);
        $this->assertEquals(80.00, $data['revenue']['previous_total']);
    }

    public function test_index_supports_week_period(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=week');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'week');
    }

    public function test_index_supports_year_period(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=year');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'year');
    }

    public function test_index_revenue_by_category_max_five_sorted_desc(): void
    {
        $categories = [];
        for ($i = 1; $i <= 6; $i++) {
            $categories[$i] = Category::factory()->create(['name' => "Category {$i}"]);
        }

        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 2100.00,
            'created_at' => Carbon::now(),
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $product = Product::factory()->create([
                'vendor_id' => $this->vendor->id,
                'category_id' => $categories[$i]->id,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'orderable_type' => Product::class,
                'orderable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $i * 100,
                'subtotal' => $i * 100,
            ]);
        }

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $revByCategory = $response->json('data.revenue_by_category');

        $this->assertCount(5, $revByCategory);
        $this->assertEquals('Category 6', $revByCategory[0]['category_name']);
        $this->assertEquals(600.00, $revByCategory[0]['revenue']);
        $names = array_column($revByCategory, 'category_name');
        $this->assertNotContains('Category 1', $names);
    }

    public function test_index_revenue_by_category_has_correct_keys(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 200.00,
            'created_at' => Carbon::now(),
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $cat = $response->json('data.revenue_by_category.0');

        $this->assertArrayHasKey('category_id', $cat);
        $this->assertArrayHasKey('category_name', $cat);
        $this->assertArrayHasKey('revenue', $cat);
        $this->assertEquals($this->category1->id, $cat['category_id']);
        $this->assertEquals('Gift Boxes', $cat['category_name']);
        $this->assertEquals(200.00, $cat['revenue']);
    }

    public function test_index_revenue_by_category_includes_bespoke_services(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 700.00,
            'created_at' => Carbon::now(),
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $service = Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Event Photography',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Service::class,
            'orderable_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 500.00,
            'subtotal' => 500.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $categories = $response->json('data.revenue_by_category');

        $this->assertCount(2, $categories);

        // Bespoke Services should be first (500 > 200)
        $this->assertEquals('Bespoke Services', $categories[0]['category_name']);
        $this->assertNull($categories[0]['category_id']);
        $this->assertEquals(500.00, $categories[0]['revenue']);

        // Gift Boxes second
        $this->assertEquals('Gift Boxes', $categories[1]['category_name']);
        $this->assertEquals(200.00, $categories[1]['revenue']);
    }

    public function test_index_top_products_max_five_sorted_desc(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 2100.00,
            'created_at' => Carbon::now(),
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $product = Product::factory()->create([
                'vendor_id' => $this->vendor->id,
                'category_id' => $this->category1->id,
                'name' => "Product {$i}",
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'orderable_type' => Product::class,
                'orderable_id' => $product->id,
                'quantity' => $i,
                'unit_price' => 100.00,
                'subtotal' => $i * 100,
            ]);
        }

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $products = $response->json('data.top_products');

        $this->assertCount(5, $products);
        $this->assertEquals('Product 6', $products[0]['name']);
    }

    public function test_index_top_products_has_correct_keys(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 300.00,
            'created_at' => Carbon::now(),
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
            'name' => 'Luxury Gift Box',
            'thumbnail' => 'storage/products/luxury.jpg',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100.00,
            'subtotal' => 300.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $item = $response->json('data.top_products.0');

        $this->assertEquals($product->id, $item['id']);
        $this->assertEquals('Luxury Gift Box', $item['name']);
        $this->assertEquals('Gift Boxes', $item['category']);
        $this->assertArrayHasKey('image_url', $item);
        $this->assertEquals(300.00, $item['revenue']);
        $this->assertEquals(1, $item['orders_count']);
        $this->assertEquals(300.00, $item['average_order_value']);
    }

    public function test_index_top_products_includes_services(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 500.00,
            'created_at' => Carbon::now(),
        ]);

        $service = Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Photography Session',
            'thumbnail' => 'storage/services/photo.jpg',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Service::class,
            'orderable_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 500.00,
            'subtotal' => 500.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $products = $response->json('data.top_products');

        $this->assertCount(1, $products);
        $this->assertEquals('Photography Session', $products[0]['name']);
        $this->assertEquals('Bespoke Services', $products[0]['category']);
        $this->assertEquals(500.00, $products[0]['revenue']);
    }

    public function test_index_image_url_null_when_no_image(): void
    {
        $order = Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'total' => 100.00,
            'created_at' => Carbon::now(),
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category1->id,
            'thumbnail' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $this->assertNull($response->json('data.top_products.0.image_url'));
    }

    public function test_index_monetary_values_have_two_decimal_precision(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 333.33,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics?period=month');

        $response->assertStatus(200);

        $revenue = $response->json('data.revenue.total');
        $this->assertIsFloat($revenue + 0.0);
        $this->assertEquals(333.33, $revenue);
    }

    public function test_index_vendor_only_sees_own_data(): void
    {
        $otherVendor = User::factory()->vendor()->create();

        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 100.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        Order::factory()->create([
            'vendor_id' => $otherVendor->id,
            'user_id' => $this->customer->id,
            'total' => 500.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/analytics');

        $response->assertStatus(200);

        $this->assertEquals(100.00, $response->json('data.revenue.total'));
        $this->assertEquals(1, $response->json('data.orders.total'));
    }

    public function test_index_admin_can_view_specific_vendor(): void
    {
        Order::factory()->create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'total' => 500.00,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/vendor/analytics?vendor_id={$this->vendor->id}");

        $response->assertStatus(200);

        $this->assertEquals(500.00, $response->json('data.revenue.total'));
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
}
