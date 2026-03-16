<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->vendor = User::factory()->vendor()->create();
    }

    public function test_admin_can_view_orders_index(): void
    {
        Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/orders');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
            ->has('statuses')
            ->has('filters')
        );
    }

    public function test_non_admin_cannot_access_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->get('/dashboard/orders');

        $response->assertRedirect();
    }

    public function test_orders_can_be_searched_by_order_number(): void
    {
        $matchingOrder = Order::factory()->pending()->create([
            'order_number' => 'ORD-SEARCHME01',
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        Order::factory()->pending()->create([
            'order_number' => 'ORD-DIFFERENT1',
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/orders?search=SEARCHME');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-SEARCHME01')
        );
    }

    public function test_orders_can_be_searched_by_customer_name(): void
    {
        $customer = User::factory()->create(['name' => 'UniqueCustomerName']);

        Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/orders?search=UniqueCustomerName');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
        );
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        Order::factory()->confirmed()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/orders?status=pending');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'pending')
        );
    }

    public function test_admin_can_view_order_detail(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/dashboard/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/show')
            ->has('order')
            ->has('allowedTransitions')
            ->where('allowedTransitions', ['confirmed'])
        );
    }

    public function test_valid_status_transition_succeeds(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/orders/{$order->id}/status", [
                'status' => 'shipped',
            ]);

        $response->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertEquals('pending', $order->status);
    }

    public function test_processing_status_update_works(): void
    {
        $order = Order::factory()->confirmed()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/orders/{$order->id}/status", [
                'status' => 'processing',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('processing', $order->status);
    }
}
