<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_register(): void
    {
        $response = $this->postJson('/api/rider/v1/auth/register', [
            'name' => 'Test Rider',
            'email' => 'rider@test.com',
            'phone' => '0241234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'vehicle_category' => 'motorbike',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['rider', 'token', 'token_type'],
            ]);

        $this->assertDatabaseHas('riders', [
            'email' => 'rider@test.com',
            'status' => 'pending',
        ]);
    }

    public function test_rider_cannot_register_with_existing_email(): void
    {
        Rider::factory()->create(['email' => 'rider@test.com']);

        $response = $this->postJson('/api/rider/v1/auth/register', [
            'name' => 'Test Rider',
            'email' => 'rider@test.com',
            'phone' => '0241234568',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'vehicle_category' => 'motorbike',
        ]);

        $response->assertStatus(422);
    }

    public function test_rider_can_login_with_email(): void
    {
        Rider::factory()->create(['email' => 'rider@test.com']);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['rider', 'token', 'token_type'],
            ]);
    }

    public function test_rider_can_login_with_phone(): void
    {
        Rider::factory()->create(['phone' => '0241234567']);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'phone' => '0241234567',
            'password' => 'password',
        ]);

        $response->assertOk();
    }

    public function test_rider_cannot_login_with_wrong_password(): void
    {
        Rider::factory()->create(['email' => 'rider@test.com']);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_rider_can_logout(): void
    {
        $rider = Rider::factory()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_unauthenticated_rider_cannot_logout(): void
    {
        $response = $this->postJson('/api/rider/v1/auth/logout');
        $response->assertStatus(401);
    }
}
