<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories' => [
                        '*' => ['id', 'name', 'slug', 'is_active'],
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data.categories'));
    }

    public function test_non_admin_cannot_access_admin_categories(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->getJson('/api/v1/admin/categories');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    public function test_guest_cannot_access_admin_categories(): void
    {
        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertStatus(401);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'New Category',
                'description' => 'A new category description',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Category created successfully.',
            ])
            ->assertJsonPath('data.category.name', 'New Category')
            ->assertJsonPath('data.category.slug', 'new-category');

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
        ]);
    }

    public function test_admin_cannot_create_category_with_duplicate_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->create(['name' => 'Existing Category']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Existing Category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_single_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', $category->name);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create([
            'name' => 'Old Name',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Name',
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category updated successfully.',
            ])
            ->assertJsonPath('data.category.name', 'Updated Name')
            ->assertJsonPath('data.category.slug', 'updated-name')
            ->assertJsonPath('data.category.is_active', true);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
        ]);
    }

    public function test_admin_can_delete_category_without_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete category with existing products. Please reassign or delete the products first.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_admin_can_create_category_with_image_and_description(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $image = UploadedFile::fake()->create('category.jpg', 100, 'image/jpeg');
        $description = 'This is a detailed category description for testing.';

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Category with Image',
                'description' => $description,
                'image' => $image,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Category created successfully.',
            ])
            ->assertJsonPath('data.category.name', 'Category with Image')
            ->assertJsonPath('data.category.description', $description);

        $category = Category::where('name', 'Category with Image')->first();
        $this->assertNotNull($category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_admin_can_update_category_with_new_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $newImage = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');
        $category = Category::factory()->create();

        // Update with new image using PUT method
        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'description' => 'Updated description',
                'image' => $newImage,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category updated successfully.',
            ]);

        $category->refresh();
        Storage::disk('public')->assertExists($category->image);
        $this->assertEquals('Updated description', $category->description);
    }

    public function test_invalid_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Invalid Image Category',
                'image' => $invalidFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_image_exceeding_max_size_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $largeImage = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Large Image Category',
                'image' => $largeImage,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }
}
