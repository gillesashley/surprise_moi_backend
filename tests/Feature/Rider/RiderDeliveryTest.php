<?php

namespace Tests\Feature\Rider;

use App\Events\DeliveryStatusUpdated;
use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RiderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_view_incoming_deliveries(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        // Broadcasting delivery request (not expired)
        DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'broadcasting',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Expired delivery request (should not appear)
        DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => Order::factory()->create(['vendor_id' => $vendor->id])->id,
            'status' => 'broadcasting',
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/deliveries/incoming');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
    }

    public function test_rider_can_accept_delivery(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'broadcasting',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/accept");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery accepted. Navigate to pickup location.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'status'],
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => 'accepted',
            'rider_id' => $rider->id,
        ]);

        Event::assertDispatched(DeliveryStatusUpdated::class);
    }

    public function test_rider_cannot_accept_already_accepted_delivery(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $otherRider = Rider::factory()->approved()->create();
        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'rider_id' => $otherRider->id,
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/accept");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'This delivery has already been accepted.',
            ]);
    }

    public function test_rider_can_confirm_pickup(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/pickup");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Pickup confirmed. Navigate to delivery location.',
                'data' => [
                    'status' => 'picked_up',
                ],
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => 'picked_up',
        ]);

        Event::assertDispatched(DeliveryStatusUpdated::class);
    }

    public function test_rider_can_confirm_delivery_with_pin(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create(['total_deliveries' => 5]);
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'delivery_pin' => '1234',
            'status' => 'shipped',
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'picked_up',
            'rider_id' => $rider->id,
            'accepted_at' => now()->subMinutes(20),
            'picked_up_at' => now()->subMinutes(10),
            'delivery_fee' => 30.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/deliver", [
                'delivery_pin' => '1234',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery confirmed! Earnings credited.',
                'data' => [
                    'status' => 'delivered',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'delivered_at',
                    'earning' => ['amount', 'status', 'available_at'],
                ],
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => 'delivered',
        ]);

        // Check earning was created
        $this->assertDatabaseHas('rider_earnings', [
            'rider_id' => $rider->id,
            'delivery_request_id' => $deliveryRequest->id,
            'amount' => '30.00',
            'status' => 'pending',
        ]);

        // Check rider total_deliveries incremented
        $this->assertEquals(6, $rider->fresh()->total_deliveries);

        // Check order marked as delivered
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);

        Event::assertDispatched(DeliveryStatusUpdated::class);
    }

    public function test_rider_cannot_confirm_delivery_with_wrong_pin(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'delivery_pin' => '1234',
            'status' => 'shipped',
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'picked_up',
            'rider_id' => $rider->id,
            'accepted_at' => now()->subMinutes(20),
            'picked_up_at' => now()->subMinutes(10),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/deliver", [
                'delivery_pin' => '9999',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid delivery PIN.',
            ]);

        // Status should not change
        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_rider_can_cancel_delivery(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'rider_id' => $rider->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/cancel", [
                'reason' => 'Vehicle broke down',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery cancelled.',
            ]);

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Vehicle broke down',
        ]);

        // Rider should be removed from order
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'rider_id' => null,
        ]);

        Event::assertDispatched(DeliveryStatusUpdated::class);
    }

    public function test_rider_can_view_delivery_history(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();

        // Create delivered deliveries
        DeliveryRequest::factory()->count(3)->create([
            'rider_id' => $rider->id,
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Create a cancelled delivery
        DeliveryRequest::factory()->create([
            'rider_id' => $rider->id,
            'vendor_id' => $vendor->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer unreachable',
        ]);

        // Active delivery should not appear in history
        DeliveryRequest::factory()->create([
            'rider_id' => $rider->id,
            'vendor_id' => $vendor->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/deliveries/history');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'status', 'pickup_address', 'dropoff_address', 'delivery_fee'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_rider_can_view_active_delivery(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        DeliveryRequest::factory()->create([
            'rider_id' => $rider->id,
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/deliveries/active');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'status', 'pickup_address', 'dropoff_address'],
            ]);
    }

    public function test_rider_can_view_active_delivery_when_none_exists(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/deliveries/active');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    public function test_rider_cannot_pickup_delivery_they_do_not_own(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $otherRider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'rider_id' => $otherRider->id,
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/pickup");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot confirm pickup for this delivery.',
            ]);
    }

    public function test_rider_cannot_cancel_delivery_they_do_not_own(): void
    {
        Event::fake([DeliveryStatusUpdated::class]);

        $rider = Rider::factory()->approved()->create();
        $otherRider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'accepted',
            'rider_id' => $otherRider->id,
            'accepted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/rider/v1/deliveries/{$deliveryRequest->id}/cancel", [
                'reason' => 'Trying to cancel someone else delivery',
            ]);

        $response->assertStatus(403);
    }
}
