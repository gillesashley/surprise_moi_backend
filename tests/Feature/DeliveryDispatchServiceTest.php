<?php

namespace Tests\Feature;

use App\Jobs\BroadcastDeliveryRequest;
use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Notifications\NewDeliveryRequestNotification;
use App\Services\DeliveryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DeliveryDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeliveryDispatchService;
    }

    public function test_create_delivery_request_without_assigned_rider_dispatches_broadcast_job(): void
    {
        Queue::fake();

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = $this->service->createDeliveryRequest(
            order: $order,
            vendorId: $vendor->id,
            pickupAddress: '123 Pickup St',
            pickupLat: 5.6037,
            pickupLng: -0.1870,
            dropoffAddress: '456 Dropoff Ave',
            dropoffLat: 5.6500,
            dropoffLng: -0.1500,
            deliveryFee: 25.00,
        );

        $this->assertInstanceOf(DeliveryRequest::class, $deliveryRequest);
        $this->assertEquals('broadcasting', $deliveryRequest->status);
        $this->assertNull($deliveryRequest->assigned_rider_id);

        Queue::assertPushed(BroadcastDeliveryRequest::class);
    }

    public function test_create_delivery_request_with_assigned_rider_notifies_rider(): void
    {
        Notification::fake();
        Queue::fake();

        $vendor = User::factory()->vendor()->create();
        $rider = Rider::factory()->approved()->online()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = $this->service->createDeliveryRequest(
            order: $order,
            vendorId: $vendor->id,
            pickupAddress: '123 Pickup St',
            pickupLat: 5.6037,
            pickupLng: -0.1870,
            dropoffAddress: '456 Dropoff Ave',
            dropoffLat: 5.6500,
            dropoffLng: -0.1500,
            deliveryFee: 25.00,
            assignedRiderId: $rider->id,
        );

        $this->assertEquals('assigned', $deliveryRequest->status);
        $this->assertEquals($rider->id, $deliveryRequest->assigned_rider_id);

        Queue::assertNotPushed(BroadcastDeliveryRequest::class);
        Notification::assertSentTo($rider, NewDeliveryRequestNotification::class);
    }

    public function test_create_delivery_request_calculates_distance(): void
    {
        Queue::fake();

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $deliveryRequest = $this->service->createDeliveryRequest(
            order: $order,
            vendorId: $vendor->id,
            pickupAddress: '123 Pickup St',
            pickupLat: 5.6037,
            pickupLng: -0.1870,
            dropoffAddress: '456 Dropoff Ave',
            dropoffLat: 5.6500,
            dropoffLng: -0.1500,
            deliveryFee: 25.00,
        );

        $this->assertGreaterThan(0, (float) $deliveryRequest->distance_km);
    }

    public function test_broadcast_to_nearby_riders_notifies_eligible_riders(): void
    {
        Notification::fake();

        $deliveryRequest = DeliveryRequest::factory()->create([
            'pickup_latitude' => 5.6037,
            'pickup_longitude' => -0.1870,
            'broadcast_attempts' => 0,
        ]);

        // Online, approved, nearby, no active deliveries
        $eligibleRider = Rider::factory()->approved()->create([
            'is_online' => true,
            'current_latitude' => 5.6040,
            'current_longitude' => -0.1875,
            'location_updated_at' => now(),
        ]);

        // Offline rider — should not be notified
        Rider::factory()->approved()->create([
            'is_online' => false,
            'current_latitude' => 5.6040,
            'current_longitude' => -0.1875,
        ]);

        $this->service->broadcastToNearbyRiders($deliveryRequest);

        Notification::assertSentTo($eligibleRider, NewDeliveryRequestNotification::class);

        $deliveryRequest->refresh();
        $this->assertEquals(1, $deliveryRequest->broadcast_attempts);
    }

    public function test_broadcast_expires_after_max_attempts(): void
    {
        Notification::fake();

        $deliveryRequest = DeliveryRequest::factory()->create([
            'broadcast_attempts' => 3,
        ]);

        $this->service->broadcastToNearbyRiders($deliveryRequest);

        $deliveryRequest->refresh();
        $this->assertEquals('expired', $deliveryRequest->status);
    }

    public function test_accept_delivery_succeeds_for_broadcasting_request(): void
    {
        $rider = Rider::factory()->approved()->online()->create();
        $order = Order::factory()->create(['vendor_id' => null]);
        $deliveryRequest = DeliveryRequest::factory()->create([
            'order_id' => $order->id,
            'status' => 'broadcasting',
        ]);

        $result = $this->service->acceptDelivery($deliveryRequest, $rider);

        $this->assertTrue($result);
        $deliveryRequest->refresh();
        $this->assertEquals('accepted', $deliveryRequest->status);
        $this->assertEquals($rider->id, $deliveryRequest->rider_id);
        $this->assertNotNull($deliveryRequest->accepted_at);

        $order->refresh();
        $this->assertEquals($rider->id, $order->rider_id);
    }

    public function test_accept_delivery_succeeds_for_assigned_rider(): void
    {
        $rider = Rider::factory()->approved()->online()->create();
        $order = Order::factory()->create(['vendor_id' => null]);
        $deliveryRequest = DeliveryRequest::factory()->create([
            'order_id' => $order->id,
            'status' => 'assigned',
            'assigned_rider_id' => $rider->id,
        ]);

        $result = $this->service->acceptDelivery($deliveryRequest, $rider);

        $this->assertTrue($result);
    }

    public function test_accept_delivery_fails_for_wrong_assigned_rider(): void
    {
        $assignedRider = Rider::factory()->approved()->create();
        $otherRider = Rider::factory()->approved()->create();
        $order = Order::factory()->create(['vendor_id' => null]);
        $deliveryRequest = DeliveryRequest::factory()->create([
            'order_id' => $order->id,
            'status' => 'assigned',
            'assigned_rider_id' => $assignedRider->id,
        ]);

        $result = $this->service->acceptDelivery($deliveryRequest, $otherRider);

        $this->assertFalse($result);
    }

    public function test_accept_delivery_fails_for_already_accepted_request(): void
    {
        $rider = Rider::factory()->approved()->create();
        $deliveryRequest = DeliveryRequest::factory()->accepted()->create();

        $result = $this->service->acceptDelivery($deliveryRequest, $rider);

        $this->assertFalse($result);
    }

    public function test_decline_delivery_re_broadcasts_assigned_request(): void
    {
        Queue::fake();

        $rider = Rider::factory()->approved()->create();
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => 'assigned',
            'assigned_rider_id' => $rider->id,
        ]);

        $this->service->declineDelivery($deliveryRequest, $rider);

        $deliveryRequest->refresh();
        $this->assertEquals('broadcasting', $deliveryRequest->status);
        $this->assertNull($deliveryRequest->assigned_rider_id);

        Queue::assertPushed(BroadcastDeliveryRequest::class);
    }

    public function test_decline_delivery_does_nothing_for_broadcasting_request(): void
    {
        Queue::fake();

        $rider = Rider::factory()->approved()->create();
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => 'broadcasting',
        ]);

        $this->service->declineDelivery($deliveryRequest, $rider);

        $deliveryRequest->refresh();
        $this->assertEquals('broadcasting', $deliveryRequest->status);

        Queue::assertNotPushed(BroadcastDeliveryRequest::class);
    }
}
