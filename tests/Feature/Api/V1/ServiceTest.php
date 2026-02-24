<?php

namespace Tests\Feature\Api\V1;

use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

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
        Storage::fake('public');
    }

    public function test_guest_can_view_service_list(): void
    {
        Service::factory()->count(3)->create(['availability' => 'available']);

        $response = $this->getJson('/api/v1/services');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'services',
                    'pagination',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_guest_can_view_single_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->getJson("/api/v1/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'service' => [
                        'id',
                        'name',
                        'description',
                        'service_type',
                        'charge_start',
                        'vendor',
                    ],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.id', $service->id);
    }

    public function test_service_list_filters_by_service_type(): void
    {
        Service::factory()->count(2)->create(['service_type' => 'cleaning', 'availability' => 'available']);
        Service::factory()->count(3)->create(['service_type' => 'plumbing', 'availability' => 'available']);

        $response = $this->getJson('/api/v1/services?service_type=cleaning');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.services');
    }

    public function test_service_list_filters_by_charge_range(): void
    {
        Service::factory()->create(['charge_start' => 50, 'availability' => 'available']);
        Service::factory()->create(['charge_start' => 150, 'availability' => 'available']);
        Service::factory()->create(['charge_start' => 250, 'availability' => 'available']);

        $response = $this->getJson('/api/v1/services?charge_min=100&charge_max=200');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.services');
    }

    public function test_service_list_searches_by_name(): void
    {
        Service::factory()->create(['name' => 'House Cleaning Service', 'availability' => 'available']);
        Service::factory()->create(['name' => 'Plumbing Repair', 'availability' => 'available']);

        $response = $this->getJson('/api/v1/services?search=House');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.services');
    }

    public function test_vendor_can_create_service_with_all_fields(): void
    {
        $serviceData = [
            'shop_id' => $this->shop->id,
            'name' => 'Professional Cleaning Service',
            'description' => 'High quality cleaning service',
            'service_type' => 'cleaning',
            'charge_start' => 50.00,
            'charge_end' => 150.00,
            'currency' => 'GHS',
            'availability' => 'available',
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Service created successfully.')
            ->assertJsonPath('data.service.name', 'Professional Cleaning Service');

        $this->assertEquals(50.00, $response->json('data.service.charge_start'));
        $this->assertEquals(150.00, $response->json('data.service.charge_end'));

        $this->assertDatabaseHas('services', [
            'name' => 'Professional Cleaning Service',
            'vendor_id' => $this->vendor->id,
            'charge_start' => 50.00,
        ]);
    }

    public function test_vendor_can_create_minimal_service(): void
    {
        $serviceData = [
            'shop_id' => $this->shop->id,
            'name' => 'Basic Service',
            'description' => 'Simple service description',
            'service_type' => 'general',
            'charge_start' => 30.00,
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.name', 'Basic Service');

        $this->assertDatabaseHas('services', [
            'name' => 'Basic Service',
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_customer_cannot_create_service(): void
    {
        $serviceData = [
            'name' => 'Test Service',
            'description' => 'Test Description',
            'service_type' => 'cleaning',
            'charge_start' => 50.00,
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_service(): void
    {
        $serviceData = [
            'name' => 'Test Service',
            'description' => 'Test Description',
            'service_type' => 'cleaning',
            'charge_start' => 50.00,
        ];

        $response = $this->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(401);
    }

    public function test_service_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'service_type', 'charge_start']);
    }

    public function test_service_creation_validates_charge_start_is_numeric(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', [
                'name' => 'Test Service',
                'description' => 'Test Description',
                'service_type' => 'cleaning',
                'charge_start' => 'invalid-price',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['charge_start']);
    }

    public function test_service_creation_validates_charge_end_greater_than_or_equal_to_charge_start(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', [
                'name' => 'Test Service',
                'description' => 'Test Description',
                'service_type' => 'cleaning',
                'charge_start' => 100.00,
                'charge_end' => 50.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['charge_end']);
    }

    public function test_vendor_can_update_own_service(): void
    {
        $service = Service::factory()->create(['vendor_id' => $this->vendor->id]);

        $updateData = [
            'name' => 'Updated Service Name',
            'charge_start' => 80.00,
            'availability' => 'booked',
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/services/{$service->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Service updated successfully.')
            ->assertJsonPath('data.service.name', 'Updated Service Name')
            ->assertJsonPath('data.service.availability', 'booked');

        $this->assertEquals(80.00, $response->json('data.service.charge_start'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Service Name',
            'charge_start' => 80.00,
            'availability' => 'booked',
        ]);
    }

    public function test_vendor_can_update_service_description(): void
    {
        $service = Service::factory()->create(['vendor_id' => $this->vendor->id, 'description' => 'Old description']);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/services/{$service->id}", [
                'description' => 'New updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.description', 'New updated description');
    }

    public function test_vendor_cannot_update_other_vendors_service(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $service = Service::factory()->create(['vendor_id' => $otherVendor->id]);

        $updateData = [
            'name' => 'Hacked Service',
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->putJson("/api/v1/services/{$service->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_update_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/services/{$service->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_own_service(): void
    {
        $service = Service::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Service deleted successfully.');

        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_vendor_cannot_delete_other_vendors_service(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $service = Service::factory()->create(['vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/services/{$service->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'deleted_at' => null,
        ]);
    }

    public function test_customer_cannot_delete_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/services/{$service->id}");

        $response->assertStatus(403);
    }

    public function test_guest_cannot_delete_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->deleteJson("/api/v1/services/{$service->id}");

        $response->assertStatus(401);
    }

    public function test_service_list_paginates_results(): void
    {
        Service::factory()->count(25)->create(['availability' => 'available']);

        $response = $this->getJson('/api/v1/services?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data.services')
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 25);
    }

    public function test_service_list_sorts_by_charge(): void
    {
        Service::factory()->create(['charge_start' => 300, 'availability' => 'available']);
        Service::factory()->create(['charge_start' => 100, 'availability' => 'available']);
        Service::factory()->create(['charge_start' => 200, 'availability' => 'available']);

        $response = $this->getJson('/api/v1/services?sort_by=charge_start&sort_order=asc');

        $response->assertStatus(200);
        $services = $response->json('data.services');
        $this->assertEquals(100, $services[0]['charge_start']);
        $this->assertEquals(200, $services[1]['charge_start']);
        $this->assertEquals(300, $services[2]['charge_start']);
    }

    public function test_service_list_filters_by_vendor(): void
    {
        $vendor1 = User::factory()->create(['role' => 'vendor']);
        $vendor2 = User::factory()->create(['role' => 'vendor']);

        Service::factory()->count(2)->create(['vendor_id' => $vendor1->id, 'availability' => 'available']);
        Service::factory()->count(3)->create(['vendor_id' => $vendor2->id, 'availability' => 'available']);

        $response = $this->getJson("/api/v1/services?vendor_id={$vendor1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.services');
    }

    public function test_service_list_filters_by_minimum_rating(): void
    {
        Service::factory()->create(['rating' => 3.5, 'availability' => 'available']);
        Service::factory()->create(['rating' => 4.5, 'availability' => 'available']);
        Service::factory()->create(['rating' => 4.8, 'availability' => 'available']);

        $response = $this->getJson('/api/v1/services?rating_min=4.0');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.services');
    }

    public function test_service_availability_can_be_set_to_unavailable(): void
    {
        $serviceData = [
            'shop_id' => $this->shop->id,
            'name' => 'Unavailable Service',
            'description' => 'Currently not available',
            'service_type' => 'maintenance',
            'charge_start' => 100.00,
            'availability' => 'unavailable',
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(201)
            ->assertJsonPath('data.service.availability', 'unavailable');

        $this->assertDatabaseHas('services', [
            'name' => 'Unavailable Service',
            'availability' => 'unavailable',
        ]);
    }

    public function test_service_charge_end_is_optional(): void
    {
        $serviceData = [
            'shop_id' => $this->shop->id,
            'name' => 'Fixed Price Service',
            'description' => 'Service with only start charge',
            'service_type' => 'consultation',
            'charge_start' => 75.00,
        ];

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/services', $serviceData);

        $response->assertStatus(201);

        $service = Service::where('name', 'Fixed Price Service')->first();
        $this->assertNull($service->charge_end);
    }
}
