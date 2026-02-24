<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Tag;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    private Category $category;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->create(['role' => 'vendor']);
        // Create approved vendor application for vendor
        VendorApplication::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => 'approved',
        ]);
        // Create shop for vendor
        $this->shop = Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->category = Category::factory()->create();
        Storage::fake('public');
    }

    public function test_guest_can_view_product_list(): void
    {
        Product::factory()->count(3)->create(['is_available' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products',
                    'pagination',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_guest_can_view_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'product' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'category',
                        'vendor',
                    ],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_product_list_filters_by_category(): void
    {
        $category1 = Category::factory()->create(['slug' => 'electronics']);
        $category2 = Category::factory()->create(['slug' => 'fashion']);

        Product::factory()->count(2)->create(['category_id' => $category1->id, 'is_available' => true]);
        Product::factory()->count(3)->create(['category_id' => $category2->id, 'is_available' => true]);

        $response = $this->getJson('/api/v1/products?category=electronics');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_product_list_filters_by_price_range(): void
    {
        Product::factory()->create(['price' => 50, 'is_available' => true]);
        Product::factory()->create(['price' => 150, 'is_available' => true]);
        Product::factory()->create(['price' => 250, 'is_available' => true]);

        $response = $this->getJson('/api/v1/products?min_price=100&max_price=200');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.products');
    }

    public function test_product_list_searches_by_name(): void
    {
        Product::factory()->create(['name' => 'iPhone 15 Pro', 'is_available' => true]);
        Product::factory()->create(['name' => 'Samsung Galaxy', 'is_available' => true]);

        $response = $this->getJson('/api/v1/products?search=iPhone');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.products');
    }

    public function test_vendor_can_create_product_with_all_fields(): void
    {
        $tag = Tag::factory()->create();

        $productData = [
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'detailed_description' => 'Detailed test description',
            'price' => 100.50,
            'discount_price' => 80.00,
            'stock' => 10,
            'is_available' => true,
            'is_featured' => false,
            'sizes' => ['Small', 'Medium', 'Large'],
            'colors' => ['Red', 'Blue'],
            'free_delivery' => true,
            'estimated_delivery_days' => '3-5 days',
            'return_policy' => '30 days return',
            'tag_ids' => [$tag->id],
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('data.product.name', 'Test Product');

        $this->assertEquals(100.50, $response->json('data.product.price'));
        $this->assertEquals(80.00, $response->json('data.product.discount_price'));

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'vendor_id' => $this->vendor->id,
            'price' => 100.50,
        ]);
    }

    public function test_vendor_can_create_minimal_product(): void
    {
        $productData = [
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Minimal Product',
            'description' => 'Simple description',
            'price' => 50.00,
            'stock' => 5,
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.name', 'Minimal Product');

        $this->assertDatabaseHas('products', [
            'name' => 'Minimal Product',
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_customer_cannot_create_product(): void
    {
        $productData = [
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 100.00,
            'stock' => 10,
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/products', $productData);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_product(): void
    {
        $productData = [
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 100.00,
            'stock' => 10,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(401);
    }

    public function test_product_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'description', 'price', 'stock']);
    }

    public function test_product_creation_validates_price_is_numeric(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'description' => 'Test Description',
                'price' => 'invalid-price',
                'stock' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_product_creation_validates_discount_price_less_than_price(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'description' => 'Test Description',
                'price' => 100.00,
                'discount_price' => 150.00,
                'stock' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_price']);
    }

    public function test_vendor_can_update_own_product(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);

        $updateData = [
            'name' => 'Updated Product Name',
            'price' => 200.00,
            'stock' => 20,
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product updated successfully.')
            ->assertJsonPath('data.product.name', 'Updated Product Name');

        $this->assertEquals(200.00, $response->json('data.product.price'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'price' => 200.00,
        ]);
    }

    public function test_vendor_can_update_product_fields(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id, 'description' => 'Old description']);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'description' => 'New updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.description', 'New updated description');
    }

    public function test_vendor_cannot_update_other_vendors_product(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $otherVendor->id]);

        $updateData = [
            'name' => 'Hacked Product',
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_update_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_own_product(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_vendor_cannot_delete_other_vendors_product(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_customer_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_guest_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(401);
    }

    public function test_product_list_paginates_results(): void
    {
        Product::factory()->count(25)->create(['is_available' => true]);

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data.products')
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 25);
    }

    public function test_product_list_sorts_by_price(): void
    {
        Product::factory()->create(['price' => 300, 'is_available' => true]);
        Product::factory()->create(['price' => 100, 'is_available' => true]);
        Product::factory()->create(['price' => 200, 'is_available' => true]);

        $response = $this->getJson('/api/v1/products?sort_by=price&sort_order=asc');

        $response->assertStatus(200);
        $products = $response->json('data.products');
        $this->assertEquals(100, $products[0]['price']);
        $this->assertEquals(200, $products[1]['price']);
        $this->assertEquals(300, $products[2]['price']);
    }

    public function test_vendor_can_update_product_availability(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id, 'is_available' => true]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'is_available' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.is_available', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_available' => false,
        ]);
    }

    public function test_discount_percentage_is_calculated_automatically(): void
    {
        $productData = [
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Discounted Product',
            'description' => 'Test Description',
            'price' => 100.00,
            'discount_price' => 80.00,
            'stock' => 10,
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        $product = Product::where('name', 'Discounted Product')->first();
        $this->assertEquals(20, $product->discount_percentage);
    }
}
