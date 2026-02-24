<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_categories(): void
    {
        Category::factory(5)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'icon',
                            'image',
                            'products_count',
                            'is_active',
                            'sort_order',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_get_all_products(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory(15)->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'price',
                            'currency',
                            'thumbnail',
                            'stock',
                            'is_available',
                            'rating',
                            'reviews_count',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);
    }

    public function test_can_filter_products_by_category(): void
    {
        $category = Category::factory()->create(['slug' => 'gift-boxes']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory(5)->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/products?category=gift-boxes');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.products')));
    }

    public function test_can_search_products(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory()->create([
            'name' => 'Beautiful Rose Bouquet',
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        Product::factory()->create([
            'name' => 'Chocolate Gift Box',
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->getJson('/api/v1/products?search=rose');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.products')));
    }

    public function test_can_filter_products_by_price_range(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory()->create([
            'price' => 50,
            'is_available' => true,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        Product::factory()->create([
            'price' => 150,
            'is_available' => true,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        Product::factory()->create([
            'price' => 250,
            'is_available' => true,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->getJson('/api/v1/products?min_price=100&max_price=200');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.products')));
    }

    public function test_can_get_single_product(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->getJson('/api/v1/products/'.$product->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'product' => [
                        'id',
                        'name',
                        'description',
                        'detailed_description',
                        'price',
                        'discount_price',
                        'currency',
                        'thumbnail',
                        'stock',
                        'is_available',
                        'rating',
                        'reviews_count',
                        'delivery_info',
                        'return_policy',
                    ],
                ],
            ]);
    }

    public function test_can_paginate_products(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory(30)->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->getJson('/api/v1/products?per_page=10&page=2');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.pagination.current_page'));
        $this->assertEquals(10, count($response->json('data.products')));
    }
}
