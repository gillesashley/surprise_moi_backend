<?php

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_interests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Interest::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/interests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'interests' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data.interests'));
    }

    public function test_non_admin_cannot_access_admin_interests(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->getJson('/api/v1/admin/interests');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    public function test_guest_cannot_access_admin_interests(): void
    {
        $response = $this->getJson('/api/v1/admin/interests');

        $response->assertStatus(401);
    }

    public function test_admin_can_create_interest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/interests', [
                'name' => 'Gardening',
                'icon' => '🌱',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Interest created successfully.',
            ])
            ->assertJsonPath('data.interest.name', 'Gardening')
            ->assertJsonPath('data.interest.icon', '🌱');

        $this->assertDatabaseHas('interests', [
            'name' => 'Gardening',
            'icon' => '🌱',
        ]);
    }

    public function test_admin_cannot_create_interest_with_duplicate_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Interest::factory()->create(['name' => 'Photography']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/interests', [
                'name' => 'Photography',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_single_interest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $interest = Interest::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/interests/{$interest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.interest.id', $interest->id)
            ->assertJsonPath('data.interest.name', $interest->name);
    }

    public function test_admin_can_update_interest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $interest = Interest::factory()->create([
            'name' => 'Old Interest',
            'icon' => '👴',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/interests/{$interest->id}", [
                'name' => 'Updated Interest',
                'icon' => '🆕',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Interest updated successfully.',
            ])
            ->assertJsonPath('data.interest.name', 'Updated Interest')
            ->assertJsonPath('data.interest.icon', '🆕');

        $this->assertDatabaseHas('interests', [
            'id' => $interest->id,
            'name' => 'Updated Interest',
            'icon' => '🆕',
        ]);
    }

    public function test_admin_can_delete_interest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $interest = Interest::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/interests/{$interest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Interest deleted successfully.',
            ]);

        $this->assertDatabaseMissing('interests', [
            'id' => $interest->id,
        ]);
    }

    public function test_deleting_interest_detaches_from_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $interest = Interest::factory()->create();

        $user->interests()->attach($interest->id);

        $this->assertDatabaseHas('interest_user', [
            'user_id' => $user->id,
            'interest_id' => $interest->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/interests/{$interest->id}");

        $this->assertDatabaseMissing('interest_user', [
            'user_id' => $user->id,
            'interest_id' => $interest->id,
        ]);
    }
}
