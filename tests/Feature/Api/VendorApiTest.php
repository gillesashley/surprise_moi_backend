<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_vendors(): void
    {
        User::factory()->count(3)->create(['role' => 'vendor']);
        User::factory()->count(2)->create(['role' => 'customer']);

        $response = $this->getJson('/api/v1/vendors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'vendors',
                    'pagination',
                ],
            ])
            ->assertJsonCount(3, 'data.vendors');
    }

    public function test_can_search_vendors(): void
    {
        User::factory()->create([
            'role' => 'vendor',
            'name' => 'Amazing Cakes Shop',
        ]);
        User::factory()->create([
            'role' => 'vendor',
            'name' => 'Flower Paradise',
        ]);

        $response = $this->getJson('/api/v1/vendors?search=cakes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.vendors');
    }

    public function test_can_view_single_vendor(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Product::factory()->count(5)->create(['vendor_id' => $vendor->id]);
        Service::factory()->count(3)->create(['vendor_id' => $vendor->id]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'vendor' => [
                        'id',
                        'name',
                        'products_count',
                        'services_count',
                    ],
                ],
            ]);

        $data = $response->json('data.vendor');
        $this->assertEquals(5, $data['products_count']);
        $this->assertEquals(3, $data['services_count']);
    }

    public function test_returns_404_for_non_vendor_user(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->getJson("/api/v1/vendors/{$customer->id}");

        $response->assertStatus(404);
    }

    public function test_can_list_vendor_products(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Product::factory()->count(5)->create([
            'vendor_id' => $vendor->id,
            'is_available' => true,
        ]);
        // Create unavailable product
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'is_available' => false,
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/products");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'vendor',
                    'products',
                    'pagination',
                ],
            ])
            // Only available products
            ->assertJsonCount(5, 'data.products');
    }

    public function test_can_search_vendor_products(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Birthday Cake',
            'is_available' => true,
        ]);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Wedding Flowers',
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/products?search=cake");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.products');
    }

    public function test_can_filter_vendor_products_by_price(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 50,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 150,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 250,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/products?min_price=100&max_price=200");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.products');
    }

    public function test_can_list_vendor_services(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Service::factory()->count(4)->create([
            'vendor_id' => $vendor->id,
            'availability' => 'available',
        ]);
        // Create unavailable service
        Service::factory()->create([
            'vendor_id' => $vendor->id,
            'availability' => 'booked',
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/services");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'vendor',
                    'services',
                    'pagination',
                ],
            ])
            // Only available services
            ->assertJsonCount(4, 'data.services');
    }

    public function test_can_filter_vendor_services_by_type(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Service::factory()->create([
            'vendor_id' => $vendor->id,
            'service_type' => 'photographer',
            'availability' => 'available',
        ]);
        Service::factory()->create([
            'vendor_id' => $vendor->id,
            'service_type' => 'caterer',
            'availability' => 'available',
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/services?service_type=photographer");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.services');
    }

    public function test_vendor_products_are_sorted_by_date_desc_by_default(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $oldProduct = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'created_at' => now()->subDays(5),
        ]);

        $newProduct = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/products");

        $response->assertStatus(200);

        $products = $response->json('data.products');
        $this->assertEquals($newProduct->id, $products[0]['id']);
    }

    public function test_can_sort_vendor_products_by_price(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 200,
            'is_available' => true,
        ]);

        $cheapProduct = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 50,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/products?sort_by=price&sort_order=asc");

        $response->assertStatus(200);

        $products = $response->json('data.products');
        $this->assertEquals($cheapProduct->id, $products[0]['id']);
    }

    public function test_can_filter_vendors_by_service_type(): void
    {
        $photographyVendor = User::factory()->create(['role' => 'vendor']);
        Service::factory()->create([
            'vendor_id' => $photographyVendor->id,
            'service_type' => 'photographer',
        ]);

        $cateringVendor = User::factory()->create(['role' => 'vendor']);
        Service::factory()->create([
            'vendor_id' => $cateringVendor->id,
            'service_type' => 'caterer',
        ]);

        $response = $this->getJson('/api/v1/vendors?service_type=photographer');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.vendors');

        $vendors = $response->json('data.vendors');
        $this->assertEquals($photographyVendor->id, $vendors[0]['id']);
    }

    public function test_vendor_list_includes_product_and_service_counts(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Product::factory()->count(3)->create(['vendor_id' => $vendor->id]);
        Service::factory()->count(2)->create(['vendor_id' => $vendor->id]);

        $response = $this->getJson('/api/v1/vendors');

        $response->assertStatus(200);

        $vendors = $response->json('data.vendors');
        $this->assertEquals(3, $vendors[0]['products_count']);
        $this->assertEquals(2, $vendors[0]['services_count']);
    }
}
