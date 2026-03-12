<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_their_addresses(): void
    {
        Address::factory()->count(3)->create(['user_id' => $this->user->id]);
        Address::factory()->count(2)->create(); // Other user's addresses

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/addresses');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'receiver_name',
                        'address_line_1',
                        'city',
                        'state',
                        'postal_code',
                        'country',
                        'full_address',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_list_addresses_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/addresses');

        $response->assertUnauthorized();
    }

    public function test_user_can_create_address(): void
    {
        $addressData = [
            'name' => 'Home',
            'address_line_1' => '1901 Thornridge Cir.',
            'city' => 'Shiloh',
            'state' => 'Hawaii',
            'postal_code' => '81063',
            'country' => 'US',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'address_line_1',
                    'city',
                    'state',
                    'postal_code',
                    'country',
                    'is_default',
                ],
            ])
            ->assertJsonPath('data.name', 'Home')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->user->id,
            'name' => 'Home',
            'city' => 'Shiloh',
        ]);
    }

    public function test_first_address_is_automatically_set_as_default(): void
    {
        $addressData = [
            'name' => 'Home',
            'address_line_1' => '123 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertCreated()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_creating_default_address_unsets_previous_default(): void
    {
        $firstAddress = Address::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        $secondAddressData = [
            'name' => 'Office',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Portland',
            'state' => 'OR',
            'postal_code' => '97201',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $secondAddressData);

        $response->assertCreated();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $firstAddress->id,
            'is_default' => false,
        ]);
    }

    public function test_user_can_view_single_address(): void
    {
        $address = Address::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $address->id);
    }

    public function test_user_cannot_view_other_users_address(): void
    {
        $otherAddress = Address::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/addresses/{$otherAddress->id}");

        $response->assertForbidden();
    }

    public function test_user_can_update_their_address(): void
    {
        $address = Address::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'name' => 'Updated Office',
            'address_line_1' => '789 New St',
            'city' => 'Seattle',
            'state' => 'WA',
            'postal_code' => '98101',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/addresses/{$address->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Office')
            ->assertJsonPath('data.city', 'Seattle');

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'name' => 'Updated Office',
            'city' => 'Seattle',
        ]);
    }

    public function test_user_cannot_update_other_users_address(): void
    {
        $otherAddress = Address::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/addresses/{$otherAddress->id}", [
                'address_line_1' => 'Hack attempt',
                'city' => 'Hack',
                'state' => 'HK',
                'postal_code' => '12345',
            ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_address(): void
    {
        $address = Address::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_deleting_default_address_sets_another_as_default(): void
    {
        $defaultAddress = Address::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        $secondAddress = Address::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/addresses/{$defaultAddress->id}");

        $response->assertOk();

        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondAddress->id,
            'is_default' => true,
        ]);
    }

    public function test_user_can_set_address_as_default(): void
    {
        $oldDefault = Address::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        $newDefault = Address::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/addresses/{$newDefault->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $oldDefault->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $newDefault->id,
            'is_default' => true,
        ]);
    }

    public function test_user_can_create_address_with_receiver_name(): void
    {
        $addressData = [
            'name' => 'Home',
            'receiver_name' => 'John Doe',
            'address_line_1' => '1901 Thornridge Cir.',
            'city' => 'Shiloh',
            'state' => 'Hawaii',
            'postal_code' => '81063',
            'country' => 'US',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertCreated()
            ->assertJsonPath('data.receiver_name', 'John Doe');

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->user->id,
            'receiver_name' => 'John Doe',
        ]);
    }

    public function test_user_can_update_receiver_name(): void
    {
        $address = Address::factory()->create([
            'user_id' => $this->user->id,
            'receiver_name' => null,
        ]);

        $updateData = [
            'name' => $address->name ?? 'Home',
            'receiver_name' => 'Jane Smith',
            'address_line_1' => $address->address_line_1,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/addresses/{$address->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.receiver_name', 'Jane Smith');

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'receiver_name' => 'Jane Smith',
        ]);
    }

    public function test_receiver_name_is_nullable(): void
    {
        $addressData = [
            'name' => 'Office',
            'address_line_1' => '456 Business Ave',
            'city' => 'Accra',
            'state' => 'Greater Accra',
            'postal_code' => '00233',
            'country' => 'GH',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertCreated()
            ->assertJsonPath('data.receiver_name', null);
    }

    public function test_address_validation_requires_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'address_line_1', 'city', 'state', 'postal_code']);
    }

    public function test_address_validation_rejects_invalid_country(): void
    {
        $addressData = [
            'address_line_1' => '123 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
            'country' => 'XX', // Invalid country code
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['country']);
    }
}
