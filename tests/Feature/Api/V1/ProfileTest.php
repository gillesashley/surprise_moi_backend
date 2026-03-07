<?php

namespace Tests\Feature\Api\V1;

use App\Models\Interest;
use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'favorite_color',
                        'favorite_music_genre',
                        'interests',
                        'personality_traits',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_basic_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'bio' => 'This is my bio',
            'favorite_color' => 'Blue',
            'favorite_music_genre' => 'Jazz',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'name' => 'Updated Name',
                        'bio' => 'This is my bio',
                        'favorite_color' => 'Blue',
                        'favorite_music_genre' => 'Jazz',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'favorite_color' => 'Blue',
            'favorite_music_genre' => 'Jazz',
        ]);
    }

    public function test_user_can_update_interests(): void
    {
        $user = User::factory()->create();
        $interests = Interest::factory()->count(3)->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'interests' => $interests->pluck('name')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(3, $user->fresh()->interests);
    }

    public function test_user_can_update_personality_traits(): void
    {
        $user = User::factory()->create();
        $traits = PersonalityTrait::factory()->count(2)->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'personality_traits' => $traits->pluck('name')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(2, $user->fresh()->personalityTraits);
    }

    public function test_user_can_update_all_preferences_at_once(): void
    {
        $user = User::factory()->create();
        $interests = Interest::factory()->count(2)->create();
        $traits = PersonalityTrait::factory()->count(2)->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'favorite_color' => 'Red',
            'favorite_music_genre' => 'Hip Hop',
            'interests' => $interests->pluck('name')->toArray(),
            'personality_traits' => $traits->pluck('name')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'favorite_color' => 'Red',
                        'favorite_music_genre' => 'Hip Hop',
                    ],
                ],
            ]);

        $user->refresh();
        $this->assertEquals('Red', $user->favorite_color);
        $this->assertEquals('Hip Hop', $user->favorite_music_genre);
        $this->assertCount(2, $user->interests);
        $this->assertCount(2, $user->personalityTraits);
    }

    public function test_syncing_interests_replaces_old_ones(): void
    {
        $user = User::factory()->create();
        $oldInterests = Interest::factory()->count(3)->create();
        $newInterests = Interest::factory()->count(2)->create();

        // First, set old interests
        $user->interests()->sync($oldInterests->pluck('id'));
        $this->assertCount(3, $user->interests);

        // Update with new interests
        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'interests' => $newInterests->pluck('name')->toArray(),
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $user->fresh()->interests);
    }

    public function test_can_get_available_interests(): void
    {
        Interest::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/profile-options/interests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'interests' => [
                        '*' => ['id', 'name', 'icon'],
                    ],
                ],
            ])
            ->assertJsonCount(5, 'data.interests');
    }

    public function test_can_get_available_personality_traits(): void
    {
        PersonalityTrait::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/profile-options/personality-traits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'personality_traits' => [
                        '*' => ['id', 'name', 'icon'],
                    ],
                ],
            ])
            ->assertJsonCount(4, 'data.personality_traits');
    }

    public function test_validation_fails_for_invalid_interest_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'interests' => [9999, 8888],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interests.0', 'interests.1']);
    }

    public function test_validation_fails_for_invalid_personality_trait_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'personality_traits' => [9999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['personality_traits.0']);
    }

    public function test_user_can_upload_avatar_via_profile_update(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
            ])
            ->assertJsonPath('data.user.avatar', function ($avatar) {
                return str_contains($avatar, 'avatars/');
            });

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('r2')->assertExists($user->avatar);
    }

    public function test_user_can_upload_avatar_via_dedicated_endpoint(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar updated successfully',
            ])
            ->assertJsonPath('data.user.avatar', function ($avatar) {
                return str_contains($avatar, 'avatars/');
            });

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('r2')->assertExists($user->avatar);
    }

    public function test_uploading_new_avatar_deletes_old_one(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Upload first avatar
        $oldFile = UploadedFile::fake()->create('old-avatar.jpg', 100, 'image/jpeg');
        $oldPath = $oldFile->store('avatars', 'r2');
        $user->update(['avatar' => $oldPath]);

        Storage::disk('r2')->assertExists($oldPath);

        // Upload new avatar
        $newFile = UploadedFile::fake()->create('new-avatar.jpg', 100, 'image/jpeg');
        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $newFile,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotEquals($oldPath, $user->avatar);
        Storage::disk('r2')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($user->avatar);
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Upload avatar first
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');
        $path = $file->store('avatars', 'r2');
        $user->update(['avatar' => $path]);

        Storage::disk('r2')->assertExists($path);

        // Delete avatar
        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/profile/avatar');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar deleted successfully',
            ]);

        $user->refresh();
        $this->assertNull($user->avatar);
        Storage::disk('r2')->assertMissing($path);
    }

    public function test_avatar_validation_rejects_invalid_file_types(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_validation_rejects_files_exceeding_size_limit(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Create a file larger than 5MB (5120KB)
        $file = UploadedFile::fake()->create('avatar.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_returns_full_url_in_response(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');
        $path = $file->store('avatars', 'r2');
        $user->update(['avatar' => $path]);

        $response = $this->actingAs($user)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.avatar', function ($avatar) {
                return str_contains($avatar, 'avatars/') && ! empty($avatar);
            });
    }

    public function test_user_can_upload_banner(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('banner.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/banner', [
                'banner' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Banner updated successfully',
            ])
            ->assertJsonPath('data.user.banner', function ($banner) {
                return str_contains($banner, 'banners/');
            });

        $user->refresh();
        $this->assertNotNull($user->banner);
        Storage::disk('r2')->assertExists($user->banner);
    }

    public function test_uploading_new_banner_deletes_old_one(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Upload first banner
        $oldFile = UploadedFile::fake()->create('old-banner.jpg', 200, 'image/jpeg');
        $oldPath = $oldFile->store('banners', 'r2');
        $user->update(['banner' => $oldPath]);

        Storage::disk('r2')->assertExists($oldPath);

        // Upload new banner
        $newFile = UploadedFile::fake()->create('new-banner.jpg', 200, 'image/jpeg');
        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/banner', [
                'banner' => $newFile,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotEquals($oldPath, $user->banner);
        Storage::disk('r2')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($user->banner);
    }

    public function test_user_can_delete_banner(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Upload banner first
        $file = UploadedFile::fake()->create('banner.jpg', 200, 'image/jpeg');
        $path = $file->store('banners', 'r2');
        $user->update(['banner' => $path]);

        Storage::disk('r2')->assertExists($path);

        // Delete banner
        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/profile/banner');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Banner deleted successfully',
            ]);

        $user->refresh();
        $this->assertNull($user->banner);
        Storage::disk('r2')->assertMissing($path);
    }

    public function test_banner_validation_rejects_invalid_file_types(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/banner', [
                'banner' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['banner']);
    }

    public function test_banner_validation_rejects_files_exceeding_size_limit(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        // Create a file larger than 5MB (5120KB)
        $file = UploadedFile::fake()->create('banner.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/banner', [
                'banner' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['banner']);
    }

    public function test_banner_accepts_webp_format(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('banner.webp', 200, 'image/webp');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/banner', [
                'banner' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Banner updated successfully',
            ]);

        $user->refresh();
        $this->assertNotNull($user->banner);
        Storage::disk('r2')->assertExists($user->banner);
    }

    public function test_banner_returns_full_url_in_response(): void
    {
        Storage::fake('r2');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('banner.jpg', 200, 'image/jpeg');
        $path = $file->store('banners', 'r2');
        $user->update(['banner' => $path]);

        $response = $this->actingAs($user)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.banner', function ($banner) {
                return str_contains($banner, 'banners/') && ! empty($banner);
            });
    }

    public function test_banner_returns_null_when_not_set(): void
    {
        $user = User::factory()->create(['banner' => null]);

        $response = $this->actingAs($user)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.banner', null);
    }
}
