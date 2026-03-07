<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WishlistApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $vendor;

    protected Product $product;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->vendor = User::factory()->create(['role' => 'vendor']);
        $this->product = Product::factory()->create(['vendor_id' => $this->vendor->id]);
        $this->service = Service::factory()->create(['vendor_id' => $this->vendor->id]);
    }

    public function test_can_add_product_to_wishlist(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->product->id,
                'item_type' => 'product',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Added to wishlist',
                'data' => [
                    'is_wishlisted' => true,
                    'item_id' => $this->product->id,
                    'item_type' => 'product',
                ],
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);
    }

    public function test_can_remove_product_from_wishlist(): void
    {
        // First add to wishlist
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        // Then remove
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->product->id,
                'item_type' => 'product',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Removed from wishlist',
                'data' => [
                    'is_wishlisted' => false,
                    'item_id' => $this->product->id,
                    'item_type' => 'product',
                ],
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);
    }

    public function test_can_add_service_to_wishlist(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->service->id,
                'item_type' => 'service',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Added to wishlist',
                'data' => [
                    'is_wishlisted' => true,
                    'item_id' => $this->service->id,
                    'item_type' => 'service',
                ],
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);
    }

    public function test_toggle_is_idempotent(): void
    {
        // Add to wishlist
        $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->product->id,
                'item_type' => 'product',
            ]);

        // Remove from wishlist
        $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->product->id,
                'item_type' => 'product',
            ]);

        // Add again - should be back to original state
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => $this->product->id,
                'item_type' => 'product',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_wishlisted' => true,
                ],
            ]);
    }

    public function test_cannot_add_nonexistent_product_to_wishlist(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => 99999,
                'item_type' => 'product',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found.',
            ]);
    }

    public function test_cannot_add_nonexistent_service_to_wishlist(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => 99999,
                'item_type' => 'service',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Service not found.',
            ]);
    }

    public function test_can_get_all_wishlist_items(): void
    {
        // Add products and services to wishlist
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/wishlist');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Wishlist retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products',
                    'services',
                    'total_count',
                ],
                'message',
            ]);

        $this->assertEquals(2, $response->json('data.total_count'));
    }

    public function test_can_filter_wishlist_by_products_only(): void
    {
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/wishlist?type=product');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.products'));
        $this->assertEmpty($response->json('data.services'));
    }

    public function test_can_filter_wishlist_by_services_only(): void
    {
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/wishlist?type=service');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.products'));
        $this->assertNotEmpty($response->json('data.services'));
    }

    public function test_can_check_multiple_items_wishlist_status(): void
    {
        // Add one product to wishlist
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        $product2 = Product::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/wishlist/check?item_ids={$this->product->id},{$product2->id}&item_type=product");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items' => [
                        '*' => [
                            'item_id',
                            'is_wishlisted',
                        ],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertTrue($items[0]['is_wishlisted']); // First product is wishlisted
        $this->assertFalse($items[1]['is_wishlisted']); // Second product is not
    }

    public function test_can_clear_all_wishlist_items(): void
    {
        // Add multiple items
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);

        $response = $this->actingAs($this->customer)
            ->deleteJson('/api/v1/wishlist/clear');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Wishlist cleared successfully',
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_can_clear_only_products_from_wishlist(): void
    {
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->service->id,
            'item_type' => 'service',
        ]);

        $response = $this->actingAs($this->customer)
            ->deleteJson('/api/v1/wishlist/clear', ['type' => 'product']);

        $response->assertStatus(200);

        // Product should be removed
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->customer->id,
            'item_type' => 'product',
        ]);

        // Service should still exist
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->customer->id,
            'item_type' => 'service',
        ]);
    }

    public function test_wishlist_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/wishlist');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/wishlist/toggle', [
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/wishlist/check?item_ids=1&item_type=product');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/wishlist/clear');
        $response->assertStatus(401);
    }

    public function test_wishlist_validates_item_type(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/wishlist/toggle', [
                'item_id' => 1,
                'item_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['item_type']);
    }

    public function test_users_only_see_their_own_wishlist(): void
    {
        $anotherCustomer = User::factory()->create(['role' => 'customer']);

        // Add item to first customer's wishlist
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        // Second customer should see empty wishlist
        $response = $this->actingAs($anotherCustomer)
            ->getJson('/api/v1/wishlist');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_count'));
    }

    public function test_product_resource_shows_wishlist_status(): void
    {
        // Add product to wishlist
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/products/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $this->product->id,
                        'is_wishlist' => true,
                    ],
                ],
            ]);
    }

    public function test_product_resource_shows_not_wishlisted_when_not_in_wishlist(): void
    {
        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/products/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $this->product->id,
                        'is_wishlist' => false,
                    ],
                ],
            ]);
    }

    public function test_unique_constraint_prevents_duplicate_wishlist_entries(): void
    {
        Wishlist::create([
            'user_id' => $this->customer->id,
            'item_id' => $this->product->id,
            'item_type' => 'product',
        ]);

        // Attempt to create a duplicate entry within a savepoint so the outer
        // transaction isn't poisoned on PostgreSQL.
        try {
            DB::connection()->transaction(function () {
                Wishlist::create([
                    'user_id' => $this->customer->id,
                    'item_id' => $this->product->id,
                    'item_type' => 'product',
                ]);
            });
        } catch (\Throwable $e) {
            // Expected: unique constraint violation
        }

        $count = Wishlist::where('user_id', $this->customer->id)
            ->where('item_id', $this->product->id)
            ->where('item_type', 'product')
            ->count();

        $this->assertSame(1, $count);
    }
}
