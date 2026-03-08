<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $vendor;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->create(['role' => 'vendor']);
        $this->category = Category::factory()->create();
        $this->shop = Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => 'Accra',
        ]);
    }

    public function test_can_filter_products_by_single_category_id(): void
    {
        $otherCategory = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $otherCategory->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/products?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_single_color(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Red', 'Pink'],
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Blue'],
        ]);

        $response = $this->getJson('/api/v1/products?color=Red');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_min_rating(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 4.5,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 3.0,
        ]);

        $response = $this->getJson('/api/v1/products?min_rating=4.0');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_popular_vendor(): void
    {
        $popularVendor = User::factory()->create(['role' => 'vendor', 'is_popular' => true]);
        $regularVendor = User::factory()->create(['role' => 'vendor', 'is_popular' => false]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $popularVendor->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $regularVendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/products?popular=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_occasion(): void
    {
        $birthday = Tag::factory()->create(['name' => 'Birthday', 'slug' => 'birthday']);
        $valentine = Tag::factory()->create(['name' => 'Valentine', 'slug' => 'valentine']);

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);
        $product1->tags()->attach($birthday);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);
        $product2->tags()->attach($valentine);

        $response = $this->getJson('/api/v1/products?occasion=Birthday');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_shop_location(): void
    {
        $accraShop = Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => 'Accra',
        ]);

        $kumasiVendor = User::factory()->create(['role' => 'vendor']);
        $kumasiShop = Shop::factory()->create([
            'vendor_id' => $kumasiVendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => 'Kumasi',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $accraShop->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $kumasiVendor->id,
            'shop_id' => $kumasiShop->id,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/products?location=Accra');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_combine_multiple_product_filters(): void
    {
        $popularVendor = User::factory()->create(['role' => 'vendor', 'is_popular' => true]);
        $birthday = Tag::factory()->create(['name' => 'Birthday', 'slug' => 'birthday']);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $popularVendor->id,
            'is_available' => true,
            'price' => 50,
            'rating' => 4.5,
            'colors' => ['Red'],
        ]);
        $product->tags()->attach($birthday);

        // Non-matching product: wrong price
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $popularVendor->id,
            'is_available' => true,
            'price' => 600,
            'rating' => 4.5,
        ]);

        $response = $this->getJson(
            "/api/v1/products?category_id={$this->category->id}&max_price=500&color=Red&min_rating=4.0&popular=1&occasion=Birthday"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_services_by_max_price(): void
    {
        Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'charge_start' => 100,
            'availability' => 'available',
        ]);

        Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'charge_start' => 600,
            'availability' => 'available',
        ]);

        $response = $this->getJson('/api/v1/services?max_price=500');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.services'));
    }

    public function test_can_filter_services_by_min_rating(): void
    {
        Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rating' => 4.5,
            'availability' => 'available',
        ]);

        Service::factory()->create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rating' => 2.0,
            'availability' => 'available',
        ]);

        $response = $this->getJson('/api/v1/services?min_rating=4.0');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.services'));
    }

    public function test_can_filter_services_by_popular_vendor(): void
    {
        $popularVendor = User::factory()->create(['role' => 'vendor', 'is_popular' => true]);
        $regularVendor = User::factory()->create(['role' => 'vendor', 'is_popular' => false]);

        $popularShop = Shop::factory()->create([
            'vendor_id' => $popularVendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $regularShop = Shop::factory()->create([
            'vendor_id' => $regularVendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Service::factory()->create([
            'vendor_id' => $popularVendor->id,
            'shop_id' => $popularShop->id,
            'availability' => 'available',
        ]);

        Service::factory()->create([
            'vendor_id' => $regularVendor->id,
            'shop_id' => $regularShop->id,
            'availability' => 'available',
        ]);

        $response = $this->getJson('/api/v1/services?popular=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.services'));
    }

    public function test_can_get_filter_locations(): void
    {
        Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => 'Kumasi',
        ]);

        // Inactive shop should be excluded
        Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'is_active' => false,
            'location' => 'Tamale',
        ]);

        // Shop with no location should be excluded
        Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => null,
        ]);

        $response = $this->getJson('/api/v1/filters/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'locations' => [
                        '*' => ['name'],
                    ],
                ],
            ])
            ->assertJson(['success' => true]);

        $locations = $response->json('data.locations');
        $locationNames = array_column($locations, 'name');

        // Should include active shops: Accra (from setUp) and Kumasi
        $this->assertContains('Accra', $locationNames);
        $this->assertContains('Kumasi', $locationNames);
        // Should exclude inactive shop and null location
        $this->assertNotContains('Tamale', $locationNames);
    }

    public function test_filter_locations_returns_unique_values(): void
    {
        // Create another shop in the same location as setUp
        $vendor2 = User::factory()->create(['role' => 'vendor']);
        Shop::factory()->create([
            'vendor_id' => $vendor2->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'location' => 'Accra',
        ]);

        $response = $this->getJson('/api/v1/filters/locations');

        $response->assertStatus(200);

        $locations = $response->json('data.locations');
        $locationNames = array_column($locations, 'name');

        // Accra should appear only once despite two shops
        $this->assertEquals(1, array_count_values($locationNames)['Accra']);
    }

    public function test_product_filters_appear_in_filters_applied(): void
    {
        $response = $this->getJson(
            '/api/v1/products?category_id=1&max_price=500&color=Red&min_rating=4.0&popular=1&occasion=Birthday&location=Accra'
        );

        $response->assertStatus(200);

        $filtersApplied = $response->json('data.filters_applied');
        $this->assertEquals('1', $filtersApplied['category_id']);
        $this->assertEquals('500', $filtersApplied['max_price']);
        $this->assertEquals('Red', $filtersApplied['color']);
        $this->assertEquals('4.0', $filtersApplied['min_rating']);
        $this->assertEquals('1', $filtersApplied['popular']);
        $this->assertEquals('Birthday', $filtersApplied['occasion']);
        $this->assertEquals('Accra', $filtersApplied['location']);
    }
}
