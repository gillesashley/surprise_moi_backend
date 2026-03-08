<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_device_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc123',
            'device_name' => 'iPhone 15',
            'platform' => 'ios',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Device token registered']);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'device_name' => 'iPhone 15',
            'platform' => 'ios',
        ]);
    }

    public function test_registering_existing_token_updates_it(): void
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'device_name' => 'Old Phone',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc123',
            'device_name' => 'New Phone',
            'platform' => 'ios',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Device token updated']);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'device_name' => 'New Phone',
            'platform' => 'ios',
        ]);
    }

    public function test_token_is_reassigned_when_different_user_registers_same_token(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user1->id,
            'token' => 'shared-device-token',
        ]);

        $response = $this->actingAs($user2)->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user2->id,
            'token' => 'shared-device-token',
        ]);
    }

    public function test_user_can_delete_device_token(): void
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-to-delete',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-to-delete',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Device token removed']);

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-token-to-delete',
        ]);
    }

    public function test_deleting_nonexistent_token_returns_success(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/v1/device-tokens', [
            'token' => 'nonexistent-token',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_store_validates_token_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_store_validates_platform_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'some-token',
            'platform' => 'windows',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_unauthenticated_user_cannot_register_token(): void
    {
        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'some-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_have_multiple_tokens(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'token-device-1',
            'device_name' => 'iPhone',
            'platform' => 'ios',
        ]);

        $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'token-device-2',
            'device_name' => 'iPad',
            'platform' => 'ios',
        ]);

        $this->assertDatabaseCount('device_tokens', 2);
        $this->assertSame(2, $user->deviceTokens()->count());
    }

    public function test_delete_only_removes_own_token(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DeviceToken::create(['user_id' => $user1->id, 'token' => 'user1-token']);
        DeviceToken::create(['user_id' => $user2->id, 'token' => 'user2-token']);

        $this->actingAs($user1)->deleteJson('/api/v1/device-tokens', [
            'token' => 'user2-token',
        ]);

        // user2's token should still exist (scoped to user1)
        $this->assertDatabaseHas('device_tokens', ['token' => 'user2-token']);
    }
}
