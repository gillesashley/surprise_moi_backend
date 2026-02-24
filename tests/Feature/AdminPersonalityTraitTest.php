<?php

namespace Tests\Feature;

use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPersonalityTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_personality_traits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        PersonalityTrait::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/personality-traits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'personality_traits' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data.personality_traits'));
    }

    public function test_non_admin_cannot_access_admin_personality_traits(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->getJson('/api/v1/admin/personality-traits');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    public function test_guest_cannot_access_admin_personality_traits(): void
    {
        $response = $this->getJson('/api/v1/admin/personality-traits');

        $response->assertStatus(401);
    }

    public function test_admin_can_create_personality_trait(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/personality-traits', [
                'name' => 'Compassionate',
                'icon' => '💗',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Personality trait created successfully.',
            ])
            ->assertJsonPath('data.personality_trait.name', 'Compassionate')
            ->assertJsonPath('data.personality_trait.icon', '💗');

        $this->assertDatabaseHas('personality_traits', [
            'name' => 'Compassionate',
            'icon' => '💗',
        ]);
    }

    public function test_admin_cannot_create_personality_trait_with_duplicate_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        PersonalityTrait::factory()->create(['name' => 'Adventurous']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/personality-traits', [
                'name' => 'Adventurous',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_single_personality_trait(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $personalityTrait = PersonalityTrait::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/personality-traits/{$personalityTrait->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.personality_trait.id', $personalityTrait->id)
            ->assertJsonPath('data.personality_trait.name', $personalityTrait->name);
    }

    public function test_admin_can_update_personality_trait(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $personalityTrait = PersonalityTrait::factory()->create([
            'name' => 'Old Trait',
            'icon' => '👴',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/personality-traits/{$personalityTrait->id}", [
                'name' => 'Updated Trait',
                'icon' => '🆕',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Personality trait updated successfully.',
            ])
            ->assertJsonPath('data.personality_trait.name', 'Updated Trait')
            ->assertJsonPath('data.personality_trait.icon', '🆕');

        $this->assertDatabaseHas('personality_traits', [
            'id' => $personalityTrait->id,
            'name' => 'Updated Trait',
            'icon' => '🆕',
        ]);
    }

    public function test_admin_can_delete_personality_trait(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $personalityTrait = PersonalityTrait::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/personality-traits/{$personalityTrait->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Personality trait deleted successfully.',
            ]);

        $this->assertDatabaseMissing('personality_traits', [
            'id' => $personalityTrait->id,
        ]);
    }

    public function test_deleting_personality_trait_detaches_from_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $personalityTrait = PersonalityTrait::factory()->create();

        $user->personalityTraits()->attach($personalityTrait->id);

        $this->assertDatabaseHas('personality_trait_user', [
            'user_id' => $user->id,
            'personality_trait_id' => $personalityTrait->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/personality-traits/{$personalityTrait->id}");

        $this->assertDatabaseMissing('personality_trait_user', [
            'user_id' => $user->id,
            'personality_trait_id' => $personalityTrait->id,
        ]);
    }
}
