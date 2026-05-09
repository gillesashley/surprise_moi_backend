<?php

namespace Tests\Feature\Api\Rider\V1;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ---------- Existing rider login ----------

    public function test_real_rider_with_valid_credentials_can_login(): void
    {
        $rider = Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['rider' => ['id', 'email'], 'token', 'token_type'],
            ])
            ->assertJsonPath('data.rider.id', $rider->id);
    }

    public function test_real_rider_wrong_password_returns_401(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);
    }

    public function test_suspended_rider_is_blocked_with_403(): void
    {
        Rider::factory()->suspended()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Your account has been suspended. Please contact support.']);
    }

    public function test_rejected_rider_is_blocked_with_403(): void
    {
        Rider::factory()->rejected()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Your application was rejected. Please contact support for details.']);
    }

    public function test_inactive_rider_is_blocked_with_403(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_pending_rider_can_still_login(): void
    {
        Rider::factory()->pending()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk();
    }

    // ---------- Super-admin fallback ----------

    public function test_admin_fallback_disabled_by_default_admin_creds_return_401(): void
    {
        config(['rider.admin_login_enabled' => false]);

        User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);

        $this->assertDatabaseMissing('riders', ['email' => 'admin@example.com']);
    }

    public function test_admin_fallback_enabled_super_admin_first_login_provisions_shadow_rider(): void
    {
        config(['rider.admin_login_enabled' => true]);

        $admin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
            'phone' => '+233200000001',
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['rider', 'token', 'token_type']]);

        $this->assertDatabaseHas('riders', [
            'user_id' => $admin->id,
            'email' => 'admin@example.com',
            'status' => 'approved',
            'vehicle_category' => 'motorbike',
        ]);
    }

    public function test_admin_fallback_second_login_reuses_shadow_rider(): void
    {
        config(['rider.admin_login_enabled' => true]);

        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $payload = ['email' => 'admin@example.com', 'password' => 'AdminPass1!'];
        $this->postJson('/api/rider/v1/auth/login', $payload)->assertOk();
        $this->postJson('/api/rider/v1/auth/login', $payload)->assertOk();

        $this->assertSame(1, Rider::where('user_id', $admin->id)->count());
    }

    public function test_admin_fallback_non_admin_user_returns_401(): void
    {
        config(['rider.admin_login_enabled' => true]);

        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('CustomerPass1!'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'CustomerPass1!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);
    }

    public function test_admin_fallback_super_admin_wrong_password_returns_401(): void
    {
        config(['rider.admin_login_enabled' => true]);

        User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('riders', ['email' => 'admin@example.com']);
    }

    public function test_shadow_rider_inert_when_flag_disabled(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);
        Rider::factory()->shadowOf($admin)->create();

        config(['rider.admin_login_enabled' => false]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertStatus(401);
    }
}
