<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_coupon(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($vendor)
            ->postJson('/api/v1/coupons', [
                'code' => 'TESTCODE123',
                'title' => 'Test Coupon',
                'type' => 'percentage',
                'value' => 10,
                'min_purchase_amount' => 100,
                'user_limit_per_user' => 1,
                'valid_from' => now()->toDateTimeString(),
                'valid_until' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
                'applicable_to' => 'all',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'type',
                    'value',
                ],
            ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'TESTCODE123',
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_user_can_view_available_coupons(): void
    {
        $user = User::factory()->create();
        Coupon::factory()->active()->count(3)->create();
        Coupon::factory()->expired()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/coupons/available');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'type', 'is_valid'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_apply_valid_coupon(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->active()->create([
            'type' => 'percentage',
            'value' => 10,
            'min_purchase_amount' => 50,
            'applicable_to' => 'all',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/coupons/apply', [
                'code' => $coupon->code,
                'subtotal' => 100,
                'items' => [
                    ['type' => 'product', 'id' => 1],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'coupon',
                'discount_amount',
                'subtotal',
                'total',
            ]);

        $this->assertEquals(10, $response->json('discount_amount'));
        $this->assertEquals(90, $response->json('total'));
    }

    public function test_cannot_apply_expired_coupon(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->expired()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/coupons/apply', [
                'code' => $coupon->code,
                'subtotal' => 100,
                'items' => [
                    ['type' => 'product', 'id' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This coupon is no longer valid.',
            ]);
    }

    public function test_cannot_delete_used_coupon(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $coupon = Coupon::factory()->create([
            'vendor_id' => $vendor->id,
            'used_count' => 5,
        ]);

        $response = $this->actingAs($vendor)
            ->deleteJson("/api/v1/coupons/{$coupon->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete a coupon that has been used.',
            ]);
    }
}
