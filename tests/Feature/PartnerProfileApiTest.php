<?php

namespace Tests\Feature;

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'customer']);
    }

    // ==================== Index Tests ====================

    public function test_user_can_list_partner_profiles(): void
    {
        PartnerProfile::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Another user's profiles should not appear
        PartnerProfile::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/partner-profiles');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // ==================== Store Tests ====================

    public function test_user_can_create_partner_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/partner-profiles', [
                'name' => 'My Wife',
                'temperament' => 'calm',
                'likes' => ['gardening', 'reading'],
                'dislikes' => ['loud music'],
                'relationship_type' => 'spouse',
                'age_range' => '25-30',
                'occasion' => 'birthday',
                'notes' => 'She loves surprises',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'temperament',
                    'likes',
                    'dislikes',
                    'relationship_type',
                    'age_range',
                    'occasion',
                    'notes',
                ],
            ])
            ->assertJsonPath('data.name', 'My Wife')
            ->assertJsonPath('data.temperament', 'calm');

        $this->assertDatabaseHas('partner_profiles', [
            'user_id' => $this->user->id,
            'name' => 'My Wife',
            'temperament' => 'calm',
        ]);
    }

    public function test_name_is_required_for_partner_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/partner-profiles', [
                'temperament' => 'calm',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_partner_profile_minimal_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/partner-profiles', [
                'name' => 'Dad',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Dad');
    }

    // ==================== Show Tests ====================

    public function test_user_can_view_partner_profile(): void
    {
        $profile = PartnerProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/partner-profiles/{$profile->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $profile->id);
    }

    public function test_user_cannot_view_other_users_profile(): void
    {
        $otherUser = User::factory()->create();
        $profile = PartnerProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/partner-profiles/{$profile->id}");

        $response->assertStatus(403);
    }

    // ==================== Update Tests ====================

    public function test_user_can_update_partner_profile(): void
    {
        $profile = PartnerProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/partner-profiles/{$profile->id}", [
                'name' => 'New Name',
                'temperament' => 'adventurous',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.temperament', 'adventurous');
    }

    public function test_user_cannot_update_other_users_profile(): void
    {
        $otherUser = User::factory()->create();
        $profile = PartnerProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/partner-profiles/{$profile->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Destroy Tests ====================

    public function test_user_can_delete_partner_profile(): void
    {
        $profile = PartnerProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/partner-profiles/{$profile->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_profiles', [
            'id' => $profile->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_profile(): void
    {
        $otherUser = User::factory()->create();
        $profile = PartnerProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/partner-profiles/{$profile->id}");

        $response->assertStatus(403);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_profiles(): void
    {
        $response = $this->getJson('/api/v1/partner-profiles');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_profile(): void
    {
        $response = $this->postJson('/api/v1/partner-profiles', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }
}
