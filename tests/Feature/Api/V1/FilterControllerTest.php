<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_filter_options(): void
    {
        // Setup test data
        $category = Category::factory()->create(['is_active' => true]);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $tag = Tag::factory()->create(['name' => 'Birthday', 'slug' => 'birthday']);

        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'price' => 100.00,
            'colors' => ['Red', 'Pink'],
        ]);

        $response = $this->getJson('/api/v1/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories',
                    'price_range' => [
                        'min',
                        'max',
                        'currency',
                    ],
                    'rating_options',
                    'colors',
                    'occasions',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_can_get_filter_categories(): void
    {
        Category::factory(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/filters/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'products_count',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data.categories'));
    }

    public function test_can_get_price_range(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'price' => 200.00,
        ]);

        $response = $this->getJson('/api/v1/filters/price-range');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'price_range' => [
                        'min' => 50.00,
                        'max' => 200.00,
                        'currency' => 'GHS',
                    ],
                ],
            ]);
    }

    public function test_can_get_available_colors(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'colors' => ['Red', 'Pink'],
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'colors' => ['Pink', 'Purple'],
        ]);

        $response = $this->getJson('/api/v1/filters/colors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'colors' => [
                        '*' => [
                            'name',
                            'hex',
                        ],
                    ],
                ],
            ]);

        // Should have unique colors: Red, Pink, Purple
        $colors = $response->json('data.colors');
        $colorNames = array_column($colors, 'name');

        $this->assertContains('Red', $colorNames);
        $this->assertContains('Pink', $colorNames);
        $this->assertContains('Purple', $colorNames);
    }

    public function test_can_get_occasions(): void
    {
        Tag::factory()->create(['name' => 'Birthday', 'slug' => 'birthday']);
        Tag::factory()->create(['name' => 'Valentine', 'slug' => 'valentine']);
        Tag::factory()->create(['name' => 'Anniversary', 'slug' => 'anniversary']);

        $response = $this->getJson('/api/v1/filters/occasions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'occasions' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'products_count',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data.occasions'));
    }

    public function test_can_get_rating_options(): void
    {
        $response = $this->getJson('/api/v1/filters/ratings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rating_options' => [
                        '*' => [
                            'label',
                            'min',
                            'max',
                        ],
                    ],
                ],
            ]);

        $ratingOptions = $response->json('data.rating_options');
        $this->assertCount(5, $ratingOptions);

        // Check the first option is "4.5 and above"
        $this->assertEquals('4.5 and above', $ratingOptions[0]['label']);
        $this->assertEquals(4.5, $ratingOptions[0]['min']);
        $this->assertNull($ratingOptions[0]['max']);
    }

    public function test_excludes_inactive_categories(): void
    {
        Category::factory()->create(['is_active' => true, 'name' => 'Active Category']);
        Category::factory()->create(['is_active' => false, 'name' => 'Inactive Category']);

        $response = $this->getJson('/api/v1/filters/categories');

        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertCount(1, $categories);
        $this->assertEquals('Active Category', $categories[0]['name']);
    }

    public function test_excludes_unavailable_products_from_colors(): void
    {
        $category = Category::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        // Available product with Red color
        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => true,
            'colors' => ['Red'],
        ]);

        // Unavailable product with Blue color
        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'is_available' => false,
            'colors' => ['Blue'],
        ]);

        $response = $this->getJson('/api/v1/filters/colors');

        $response->assertStatus(200);

        $colors = $response->json('data.colors');
        $colorNames = array_column($colors, 'name');

        $this->assertContains('Red', $colorNames);
        $this->assertNotContains('Blue', $colorNames);
    }
}
