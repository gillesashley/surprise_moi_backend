<?php

namespace Tests\Feature\Rider;

use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_view_dashboard(): void
    {
        $rider = Rider::factory()->approved()->create([
            'is_online' => true,
            'total_deliveries' => 15,
            'average_rating' => 4.50,
        ]);
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_online',
                    'today_earnings',
                    'today_deliveries',
                    'total_earnings',
                    'total_deliveries',
                    'average_rating',
                    'available_balance',
                    'pending_balance',
                    'active_delivery',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_online' => true,
                    'total_deliveries' => 15,
                ],
            ]);
    }

    public function test_rider_can_toggle_online(): void
    {
        $rider = Rider::factory()->approved()->create(['is_online' => false]);
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/dashboard/toggle-online');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'You are now online.',
                'data' => ['is_online' => true],
            ]);

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'is_online' => true,
        ]);

        // Toggle back offline
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/dashboard/toggle-online');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'You are now offline.',
                'data' => ['is_online' => false],
            ]);
    }

    public function test_rider_can_update_location(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/dashboard/location', [
                'latitude' => 5.6037,
                'longitude' => -0.1870,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $rider->refresh();
        $this->assertEquals('5.6037000', $rider->current_latitude);
        $this->assertEquals('-0.1870000', $rider->current_longitude);
        $this->assertNotNull($rider->location_updated_at);
    }

    public function test_rider_can_update_device_token(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/rider/v1/dashboard/device-token', [
                'device_token' => 'fcm_test_token_12345',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Device token updated.',
            ]);

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'device_token' => 'fcm_test_token_12345',
        ]);
    }

    public function test_unapproved_rider_cannot_access_dashboard(): void
    {
        $rider = Rider::factory()->pending()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account is pending approval.',
            ]);
    }

    public function test_dashboard_shows_today_earnings_and_deliveries(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $vendor = User::factory()->vendor()->create();
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        // Create a delivered delivery request for today
        $delivery = DeliveryRequest::factory()->create([
            'rider_id' => $rider->id,
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => 'delivered',
            'delivered_at' => now(),
            'delivery_fee' => 25.00,
        ]);

        // Create an earning for today
        RiderEarning::factory()->create([
            'rider_id' => $rider->id,
            'order_id' => $order->id,
            'delivery_request_id' => $delivery->id,
            'amount' => 25.00,
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/dashboard');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'today_earnings' => 25.00,
                    'today_deliveries' => 1,
                ],
            ]);
    }

    public function test_update_location_validates_coordinates(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/dashboard/location', [
                'latitude' => 100,
                'longitude' => -200,
            ]);

        $response->assertStatus(422);
    }

    public function test_update_device_token_requires_token(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/rider/v1/dashboard/device-token', []);

        $response->assertStatus(422);
    }
}
