<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create(['slug' => 'flowers']);
        $this->vendor = User::factory()->create(['role' => 'vendor']);
    }

    public function test_can_filter_products_by_multiple_categories(): void
    {
        $category1 = Category::factory()->create(['slug' => 'bouquets']);
        $category2 = Category::factory()->create(['slug' => 'indoor-plants']);
        $category3 = Category::factory()->create(['slug' => 'gift-boxes']);

        Product::factory()->create([
            'category_id' => $category1->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category2->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category3->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/products?category=bouquets,indoor-plants');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_can_filter_products_by_category_ids(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category1->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category2->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/products?category_ids={$category1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_color(): void
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
            'colors' => ['Blue', 'Purple'],
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Pink', 'White'],
        ]);

        $response = $this->getJson('/api/v1/products?colors=pink');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_can_filter_products_by_multiple_colors(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Red'],
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Blue'],
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'colors' => ['Green'],
        ]);

        $response = $this->getJson('/api/v1/products?colors=red,blue');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_can_filter_products_by_tags(): void
    {
        $birthdayTag = Tag::factory()->create(['name' => 'Birthday', 'slug' => 'birthday']);
        $valentineTag = Tag::factory()->create(['name' => 'Valentine', 'slug' => 'valentine']);

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);
        $product1->tags()->attach($birthdayTag);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);
        $product2->tags()->attach($valentineTag);

        $response = $this->getJson('/api/v1/products?tags=birthday');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_tag_ids(): void
    {
        $tag = Tag::factory()->create();

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);
        $product1->tags()->attach($tag);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/products?tag_ids={$tag->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_rating_range(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 4.8,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 4.2,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 3.5,
        ]);

        // Filter for 4.0 - 4.5 range
        $response = $this->getJson('/api/v1/products?rating_min=4.0&rating_max=4.5');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_by_rating_min_only(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 4.8,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 4.6,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'rating' => 3.5,
        ]);

        // Filter for 4.5 and above
        $response = $this->getJson('/api/v1/products?rating_min=4.5');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_can_filter_featured_products(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'is_featured' => true,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $response = $this->getJson('/api/v1/products?featured=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_with_free_delivery(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'free_delivery' => true,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'free_delivery' => false,
        ]);

        $response = $this->getJson('/api/v1/products?free_delivery=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_filter_products_with_discount(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'price' => 100,
            'discount_price' => 80,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'price' => 50,
            'discount_price' => null,
        ]);

        $response = $this->getJson('/api/v1/products?has_discount=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_can_combine_multiple_filters(): void
    {
        $birthdayTag = Tag::factory()->create(['slug' => 'birthday']);

        // Product that matches all filters
        $matchingProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'price' => 50,
            'rating' => 4.5,
            'colors' => ['Red'],
        ]);
        $matchingProduct->tags()->attach($birthdayTag);

        // Product that doesn't match price filter
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'price' => 200,
            'rating' => 4.5,
            'colors' => ['Red'],
        ]);

        // Product that doesn't match rating filter
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'price' => 50,
            'rating' => 3.0,
            'colors' => ['Red'],
        ]);

        $response = $this->getJson('/api/v1/products?min_price=20&max_price=100&rating_min=4.0&colors=red&tags=birthday');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
        $this->assertEquals($matchingProduct->id, $response->json('data.products.0.id'));
    }

    public function test_filters_applied_are_returned_in_response(): void
    {
        $response = $this->getJson('/api/v1/products?min_price=20&max_price=100&colors=red&tags=birthday');

        $response->assertStatus(200);

        $filtersApplied = $response->json('data.filters_applied');

        $this->assertEquals('20', $filtersApplied['min_price']);
        $this->assertEquals('100', $filtersApplied['max_price']);
        $this->assertEquals('red', $filtersApplied['colors']);
        $this->assertEquals('birthday', $filtersApplied['tags']);
    }

    public function test_can_sort_products_by_discount_percentage(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'discount_percentage' => 10,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'discount_percentage' => 30,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_id' => $this->vendor->id,
            'is_available' => true,
            'discount_percentage' => 20,
        ]);

        $response = $this->getJson('/api/v1/products?sort_by=discount_percentage&sort_order=desc');

        $response->assertStatus(200);

        $products = $response->json('data.products');
        $this->assertEquals(30, $products[0]['discount_percentage']);
        $this->assertEquals(20, $products[1]['discount_percentage']);
        $this->assertEquals(10, $products[2]['discount_percentage']);
    }
}
