<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_addresses(): void
    {
        $user = User::factory()->create();
        Address::factory()->count(3)->create(['user_id' => $user->id]);

        // Create addresses for another user (should not be visible)
        Address::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/addresses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'label',
                        'address_line_1',
                        'address_line_2',
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
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_create_address(): void
    {
        $user = User::factory()->create();

        $addressData = [
            'label' => 'Home',
            'address_line_1' => '1901 Thornridge Cir',
            'address_line_2' => 'Apt 4B',
            'city' => 'Shiloh',
            'state' => 'Hawaii',
            'postal_code' => '81063',
            'country' => 'US',
            'is_default' => true,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/addresses', $addressData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Address created successfully',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'label', 'address_line_1', 'city', 'is_default'],
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
            'city' => 'Shiloh',
            'is_default' => true,
        ]);
    }

    public function test_first_address_becomes_default_automatically(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/addresses', [
                'label' => 'Office',
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'US',
                'is_default' => false,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'is_default' => true,
        ]);
    }

    public function test_setting_new_default_unsets_previous_default(): void
    {
        $user = User::factory()->create();
        $existingDefault = Address::factory()->default()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/addresses', [
                'label' => 'New Home',
                'address_line_1' => '456 Oak Ave',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal_code' => '90001',
                'country' => 'US',
                'is_default' => true,
            ]);

        $response->assertStatus(201);

        $existingDefault->refresh();
        $this->assertFalse($existingDefault->is_default);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'label' => 'New Home',
            'is_default' => true,
        ]);
    }

    public function test_user_can_view_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $address->id,
                    'label' => $address->label,
                ],
            ]);
    }

    public function test_user_cannot_view_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
    }

    public function test_user_can_update_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'city' => 'Old City',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/addresses/{$address->id}", [
                'label' => 'Updated Home',
                'address_line_1' => $address->address_line_1,
                'city' => 'New City',
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address updated successfully',
            ]);

        $address->refresh();
        $this->assertEquals('Updated Home', $address->label);
        $this->assertEquals('New City', $address->city);
    }

    public function test_user_can_delete_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);

        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('user_addresses', ['id' => $address->id]);
    }

    public function test_deleting_default_address_sets_another_as_default(): void
    {
        $user = User::factory()->create();
        $defaultAddress = Address::factory()->default()->create(['user_id' => $user->id]);
        $otherAddress = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/addresses/{$defaultAddress->id}");

        $response->assertStatus(200);

        $otherAddress->refresh();
        $this->assertTrue($otherAddress->is_default);
    }

    public function test_user_can_set_address_as_default(): void
    {
        $user = User::factory()->create();
        $defaultAddress = Address::factory()->default()->create(['user_id' => $user->id]);
        $otherAddress = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/addresses/{$otherAddress->id}/set-default");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Default address updated successfully',
            ]);

        $defaultAddress->refresh();
        $otherAddress->refresh();

        $this->assertFalse($defaultAddress->is_default);
        $this->assertTrue($otherAddress->is_default);
    }

    public function test_user_cannot_set_another_users_address_as_default(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/addresses/{$address->id}/set-default");

        $response->assertStatus(403);
    }

    public function test_address_validation_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/addresses', [
                'label' => '',
                'address_line_1' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address_line_1', 'city', 'state', 'postal_code']);
    }

    public function test_unauthenticated_user_cannot_access_addresses(): void
    {
        $response = $this->getJson('/api/v1/addresses');

        $response->assertStatus(401);
    }

    public function test_addresses_are_ordered_by_default_and_creation_date(): void
    {
        $user = User::factory()->create();

        $oldAddress = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
            'created_at' => now()->subDays(2),
        ]);

        $defaultAddress = Address::factory()->default()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDay(),
        ]);

        $newestAddress = Address::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/addresses');

        $response->assertStatus(200);

        $addresses = $response->json('data');

        // Default should be first
        $this->assertEquals($defaultAddress->id, $addresses[0]['id']);
    }
}
