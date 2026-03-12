<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_view_edit_category_page(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($superAdmin)
            ->get("/dashboard/categories/{$category->id}/edit");

        $response->assertStatus(200);
        $response->assertSee($category->name);
    }

    public function test_non_admin_cannot_view_edit_category_page(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::factory()->create();

        $response = $this->actingAs($customer)
            ->get("/dashboard/categories/{$category->id}/edit");

        $response->assertStatus(302);
    }

    public function test_super_admin_can_update_category_without_image(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create([
            'name' => 'Old Name',
            'description' => 'Old description',
        ]);

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => 'Updated Name',
                'type' => 'product',
                'description' => 'Updated description',
                'sort_order' => 5,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'description' => 'Updated description',
            'sort_order' => 5,
        ]);
    }

    public function test_super_admin_can_update_category_with_new_image(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create([
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);
        $newImage = UploadedFile::fake()->create('new-image.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => 'Test Category',
                'type' => 'product',
                'description' => 'Test description',
                'image' => $newImage,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $category->refresh();

        $this->assertNotNull($category->image);
        Storage::disk()->assertExists($category->image);
        $this->assertTrue(str_contains($category->image, 'categories/'));
    }

    public function test_super_admin_can_replace_existing_category_image(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $oldImage = UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg');
        $newImage = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');

        $category = Category::factory()->create();
        $oldImagePath = $oldImage->store('categories');
        $category->update(['image' => $oldImagePath]);

        // Verify old image exists
        Storage::disk()->assertExists($oldImagePath);

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => $category->description,
                'image' => $newImage,
                'is_active' => $category->is_active,
            ]);

        $response->assertRedirect();
        $category->refresh();

        // Verify new image is saved
        $this->assertNotNull($category->image);
        Storage::disk()->assertExists($category->image);
        // Verify old image was deleted
        Storage::disk()->assertMissing($oldImagePath);
    }

    public function test_category_update_with_image_removes_old_image(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $oldImage = UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg');
        $newImage = UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg');

        $category = Category::factory()->create();
        $oldImagePath = $oldImage->store('categories');
        $category->update(['image' => $oldImagePath]);

        $oldImagePath = $category->image;

        $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => 'New description',
                'image' => $newImage,
                'is_active' => true,
            ]);

        Storage::disk()->assertMissing($oldImagePath);
    }

    public function test_invalid_image_format_is_rejected_on_update(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create();
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => $category->description,
                'image' => $invalidFile,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_oversized_image_is_rejected_on_update(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create();
        $largeImage = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => $category->description,
                'image' => $largeImage,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_super_admin_can_view_create_category_page(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get('/dashboard/categories/create');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_view_create_category_page(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->get('/dashboard/categories/create');

        $response->assertStatus(302);
    }

    public function test_super_admin_can_create_category_with_image(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $image = UploadedFile::fake()->create('category.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($superAdmin)
            ->post('/dashboard/categories', [
                'name' => 'New Category',
                'type' => 'product',
                'description' => 'A brand new category',
                'image' => $image,
                'sort_order' => 10,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'A brand new category',
        ]);

        $category = Category::where('name', 'New Category')->first();
        $this->assertNotNull($category->image);
        Storage::disk()->assertExists($category->image);
    }

    public function test_super_admin_can_delete_category_with_image(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $image = UploadedFile::fake()->create('category.jpg', 100, 'image/jpeg');

        $category = Category::factory()->create();
        $imagePath = $image->store('categories');
        $category->update(['image' => $imagePath]);

        Storage::disk()->assertExists($imagePath);

        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/categories/{$category->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        Storage::disk()->assertMissing($imagePath);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        Category::factory()->create(['name' => 'Existing Category']);

        $response = $this->actingAs($superAdmin)
            ->post('/dashboard/categories', [
                'name' => 'Existing Category',
                'type' => 'product',
                'description' => 'Test',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_description_length_is_validated(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::factory()->create();
        $longDescription = str_repeat('a', 1001);

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => $longDescription,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_super_admin_can_create_category_with_icon(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $icon = UploadedFile::fake()->create('icon.png', 50, 'image/png');

        $response = $this->actingAs($superAdmin)
            ->post('/dashboard/categories', [
                'name' => 'Icon Category',
                'type' => 'product',
                'description' => 'Category with icon',
                'icon' => $icon,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $category = Category::where('name', 'Icon Category')->first();
        $this->assertNotNull($category->icon);
        $this->assertTrue(str_contains($category->icon, 'categories/icons/'));
        Storage::disk()->assertExists($category->icon);
    }

    public function test_super_admin_can_update_category_icon(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $oldIcon = UploadedFile::fake()->create('old-icon.png', 50, 'image/png');
        $newIcon = UploadedFile::fake()->create('new-icon.png', 50, 'image/png');

        $category = Category::factory()->create();
        $oldIconPath = $oldIcon->store('categories/icons');
        $category->update(['icon' => $oldIconPath]);

        Storage::disk()->assertExists($oldIconPath);

        $response = $this->actingAs($superAdmin)
            ->put("/dashboard/categories/{$category->id}", [
                'name' => $category->name,
                'type' => 'product',
                'description' => $category->description,
                'icon' => $newIcon,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $category->refresh();

        $this->assertNotNull($category->icon);
        Storage::disk()->assertExists($category->icon);
        Storage::disk()->assertMissing($oldIconPath);
    }

    public function test_non_png_icon_is_rejected(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $invalidIcon = UploadedFile::fake()->create('icon.jpg', 50, 'image/jpeg');

        $response = $this->actingAs($superAdmin)
            ->post('/dashboard/categories', [
                'name' => 'Bad Icon Category',
                'type' => 'product',
                'icon' => $invalidIcon,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('icon');
    }

    public function test_delete_category_also_deletes_icon(): void
    {
        Storage::fake('public');
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $icon = UploadedFile::fake()->create('icon.png', 50, 'image/png');

        $category = Category::factory()->create();
        $iconPath = $icon->store('categories/icons');
        $category->update(['icon' => $iconPath]);

        Storage::disk()->assertExists($iconPath);

        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/categories/{$category->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        Storage::disk()->assertMissing($iconPath);
    }
}
