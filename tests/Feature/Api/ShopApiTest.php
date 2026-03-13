<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        // Create approved vendor application for vendor
        VendorApplication::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => 'approved',
        ]);
    }

    public function test_vendor_can_create_shop(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/shops', [
                'category_id' => $category->id,
                'name' => 'My Awesome Shop',
                'owner_name' => 'John Owner',
                'description' => 'Best shop in town',
                'location' => 'Accra',
                'phone' => '0244123456',
                'email' => 'shop@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'shop' => [
                        'id',
                        'name',
                        'owner_name',
                        'slug',
                        'description',
                        'location',
                        'phone',
                        'email',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('shops', [
            'name' => 'My Awesome Shop',
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_can_list_public_shops(): void
    {
        Shop::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/shops');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shops',
                    'pagination',
                ],
            ]);
    }

    public function test_vendor_can_view_their_shops(): void
    {
        Shop::factory()->count(2)->create(['vendor_id' => $this->vendor->id]);
        Shop::factory()->create(); // Another vendor's shop

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/my-shops');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.shops');
    }

    public function test_vendor_can_update_their_shop(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'name' => 'Updated Shop Name',
                'location' => 'Kumasi',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shop updated successfully.',
            ]);

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Updated Shop Name',
            'location' => 'Kumasi',
        ]);
    }

    public function test_vendor_cannot_update_other_vendors_shop(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::factory()->create(['vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'name' => 'Hacked Shop Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_their_shop(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/shops/{$shop->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shop deleted successfully.',
            ]);

        $this->assertSoftDeleted('shops', ['id' => $shop->id]);
    }

    public function test_can_view_shop_products(): void
    {
        $shop = Shop::factory()
            ->hasProducts(3)
            ->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/shops/{$shop->id}/products");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shop',
                    'products',
                    'pagination',
                ],
            ]);
    }

    public function test_can_view_shop_services(): void
    {
        $shop = Shop::factory()
            ->hasServices(2)
            ->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/shops/{$shop->id}/services");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shop',
                    'services',
                    'pagination',
                ],
            ]);
    }

    public function test_vendor_can_create_shop_with_logo(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $logo = UploadedFile::fake()->create('shop-logo.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/shops', [
                'category_id' => $category->id,
                'name' => 'Shop With Logo',
                'owner_name' => 'Jane Owner',
                'description' => 'Shop with a logo',
                'location' => 'Kumasi',
                'phone' => '0501234567',
                'email' => 'logo@shop.com',
                'logo' => $logo,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shop.name', 'Shop With Logo')
            ->assertJsonPath('data.shop.owner_name', 'Jane Owner');

        // Verify logo was uploaded
        Storage::disk()->assertExists('shops/logos/'.$logo->hashName());

        $this->assertDatabaseHas('shops', [
            'name' => 'Shop With Logo',
            'owner_name' => 'Jane Owner',
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_owner_name_is_returned_in_response(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/shops', [
                'category_id' => $category->id,
                'name' => 'Test Shop',
                'owner_name' => 'Test Owner Name',
                'description' => 'Testing owner name',
                'location' => 'Accra',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.shop.owner_name', 'Test Owner Name');

        // Verify it's also in the database
        $shop = Shop::where('name', 'Test Shop')->first();
        $this->assertEquals('Test Owner Name', $shop->owner_name);
    }

    public function test_category_is_required_for_shop_creation(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/shops', [
                'name' => 'Shop Without Category',
                'owner_name' => 'Owner Name',
                'description' => 'Missing category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_owner_name_is_required_for_shop_creation(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/shops', [
                'category_id' => $category->id,
                'name' => 'Shop Without Owner',
                'description' => 'Missing owner name',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_name']);
    }

    public function test_my_shops_response_includes_service_hours(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/my-shops');

        $response->assertStatus(200);

        $shopData = $response->json('data.shops.0');
        $this->assertArrayHasKey('service_hours', $shopData);
        $this->assertArrayHasKey('monday', $shopData['service_hours']);
        $this->assertArrayHasKey('sunday', $shopData['service_hours']);
    }

    public function test_shop_show_response_includes_service_hours(): void
    {
        $shop = Shop::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/shops/{$shop->id}");

        $response->assertStatus(200);

        $shopData = $response->json('data.shop');
        $this->assertArrayHasKey('service_hours', $shopData);
        $this->assertArrayHasKey('monday', $shopData['service_hours']);
    }

    public function test_new_shop_returns_default_service_hours(): void
    {
        $shop = Shop::factory()->create([
            'vendor_id' => $this->vendor->id,
            'service_hours' => null,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/my-shops');

        $response->assertStatus(200);

        $serviceHours = $response->json('data.shops.0.service_hours');

        // Default: Mon-Fri open 09:00-17:00, Sat-Sun closed
        $this->assertTrue($serviceHours['monday']['is_open']);
        $this->assertEquals('09:00', $serviceHours['monday']['open']);
        $this->assertEquals('17:00', $serviceHours['monday']['close']);
        $this->assertFalse($serviceHours['saturday']['is_open']);
        $this->assertNull($serviceHours['saturday']['open']);
        $this->assertFalse($serviceHours['sunday']['is_open']);
        $this->assertNull($serviceHours['sunday']['open']);
    }

    public function test_vendor_can_update_service_hours(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $serviceHours = [
            'monday'    => ['is_open' => true,  'open' => '08:00', 'close' => '18:00'],
            'tuesday'   => ['is_open' => true,  'open' => '08:00', 'close' => '18:00'],
            'wednesday' => ['is_open' => true,  'open' => '08:00', 'close' => '18:00'],
            'thursday'  => ['is_open' => true,  'open' => '08:00', 'close' => '18:00'],
            'friday'    => ['is_open' => true,  'open' => '08:00', 'close' => '18:00'],
            'saturday'  => ['is_open' => true,  'open' => '09:00', 'close' => '14:00'],
            'sunday'    => ['is_open' => false, 'open' => null,    'close' => null],
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $serviceHours,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $shop->refresh();
        $this->assertTrue($shop->service_hours['monday']['is_open']);
        $this->assertEquals('08:00', $shop->service_hours['monday']['open']);
        $this->assertEquals('18:00', $shop->service_hours['monday']['close']);
        $this->assertFalse($shop->service_hours['sunday']['is_open']);
    }

    public function test_omitting_service_hours_preserves_existing(): void
    {
        $existingHours = [
            'monday'    => ['is_open' => true,  'open' => '10:00', 'close' => '20:00'],
            'tuesday'   => ['is_open' => true,  'open' => '10:00', 'close' => '20:00'],
            'wednesday' => ['is_open' => true,  'open' => '10:00', 'close' => '20:00'],
            'thursday'  => ['is_open' => true,  'open' => '10:00', 'close' => '20:00'],
            'friday'    => ['is_open' => true,  'open' => '10:00', 'close' => '20:00'],
            'saturday'  => ['is_open' => false, 'open' => null,    'close' => null],
            'sunday'    => ['is_open' => false, 'open' => null,    'close' => null],
        ];

        $shop = Shop::factory()->withServiceHours($existingHours)->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'name' => 'Updated Name Only',
            ]);

        $response->assertStatus(200);

        $shop->refresh();
        $this->assertEquals('10:00', $shop->service_hours['monday']['open']);
        $this->assertEquals('20:00', $shop->service_hours['monday']['close']);
    }

    public function test_service_hours_rejects_missing_days(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => [
                    'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '17:00'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_service_hours_rejects_close_before_open(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $allDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $allDays[$day] = ['is_open' => false, 'open' => null, 'close' => null];
        }
        $allDays['monday'] = ['is_open' => true, 'open' => '17:00', 'close' => '09:00'];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $allDays,
            ]);

        $response->assertStatus(422);
    }

    public function test_service_hours_rejects_open_without_times(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $allDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $allDays[$day] = ['is_open' => false, 'open' => null, 'close' => null];
        }
        $allDays['monday'] = ['is_open' => true, 'open' => null, 'close' => null];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $allDays,
            ]);

        $response->assertStatus(422);
    }

    public function test_service_hours_rejects_invalid_time_format(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $allDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $allDays[$day] = ['is_open' => false, 'open' => null, 'close' => null];
        }
        $allDays['monday'] = ['is_open' => true, 'open' => '9am', 'close' => '5pm'];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $allDays,
            ]);

        $response->assertStatus(422);
    }

    public function test_service_hours_rejects_extra_day_keys(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $allDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $allDays[$day] = ['is_open' => false, 'open' => null, 'close' => null];
        }
        $allDays['holiday'] = ['is_open' => false, 'open' => null, 'close' => null];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $allDays,
            ]);

        $response->assertStatus(422);
    }

    public function test_service_hours_rejects_times_when_closed(): void
    {
        $shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);

        $allDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $allDays[$day] = ['is_open' => false, 'open' => null, 'close' => null];
        }
        $allDays['monday'] = ['is_open' => false, 'open' => '09:00', 'close' => '17:00'];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/shops/{$shop->id}", [
                'service_hours' => $allDays,
            ]);

        $response->assertStatus(422);
    }
}
