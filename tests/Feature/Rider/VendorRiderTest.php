<?php

namespace Tests\Feature\Rider;

use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Models\VendorRider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VendorRiderTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->vendor()->create();
    }

    public function test_vendor_can_list_preferred_riders(): void
    {
        $rider = Rider::factory()->approved()->create();
        VendorRider::factory()->create([
            'vendor_id' => $this->vendor->id,
            'rider_id' => $rider->id,
            'nickname' => 'Fast Kwame',
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/riders');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'nickname', 'is_default', 'rider', 'created_at'],
                ],
            ]);
    }

    public function test_vendor_can_add_preferred_rider(): void
    {
        $rider = Rider::factory()->approved()->create([
            'phone' => '0241112222',
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/riders', [
                'phone' => '0241112222',
                'nickname' => 'My Rider',
                'is_default' => true,
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Rider added to your preferred list.',
            ]);

        $this->assertDatabaseHas('vendor_riders', [
            'vendor_id' => $this->vendor->id,
            'rider_id' => $rider->id,
            'nickname' => 'My Rider',
            'is_default' => true,
        ]);
    }

    public function test_vendor_can_remove_preferred_rider(): void
    {
        $rider = Rider::factory()->approved()->create();
        $vendorRider = VendorRider::factory()->create([
            'vendor_id' => $this->vendor->id,
            'rider_id' => $rider->id,
        ]);

        $response = $this->actingAs($this->vendor)
            ->deleteJson("/api/v1/vendor/riders/{$vendorRider->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Rider removed from your preferred list.',
            ]);

        $this->assertDatabaseMissing('vendor_riders', [
            'id' => $vendorRider->id,
        ]);
    }

    public function test_vendor_can_dispatch_delivery_to_rider(): void
    {
        Queue::fake();

        $rider = Rider::factory()->approved()->online()->create();
        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor)
            ->postJson("/api/v1/vendor/orders/{$order->id}/dispatch", [
                'rider_id' => $rider->id,
                'pickup_address' => '123 Vendor St, Accra',
                'pickup_latitude' => 5.6037,
                'pickup_longitude' => -0.1870,
                'dropoff_address' => '456 Customer Ave, Accra',
                'dropoff_latitude' => 5.6500,
                'dropoff_longitude' => -0.1500,
                'delivery_fee' => 25.00,
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery assigned to rider. Waiting for acceptance.',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'order_id', 'status', 'pickup_address', 'dropoff_address', 'delivery_fee'],
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'assigned_rider_id' => $rider->id,
            'status' => 'assigned',
        ]);
    }

    public function test_vendor_can_dispatch_open_delivery(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor)
            ->postJson("/api/v1/vendor/orders/{$order->id}/dispatch", [
                'pickup_address' => '123 Vendor St, Accra',
                'pickup_latitude' => 5.6037,
                'pickup_longitude' => -0.1870,
                'dropoff_address' => '456 Customer Ave, Accra',
                'dropoff_latitude' => 5.6500,
                'dropoff_longitude' => -0.1500,
                'delivery_fee' => 20.00,
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery request broadcast to nearby riders.',
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'broadcasting',
        ]);
    }
}
