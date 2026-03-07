<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\WawVideo;
use App\Models\WawVideoLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WawVideoApiTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        VendorApplication::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => 'approved',
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
    }

    // ── Feed Endpoint ──────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_feed(): void
    {
        $this->getJson('/api/v1/waw-videos')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_get_feed(): void
    {
        WawVideo::factory()->count(3)->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'videos' => [
                        '*' => [
                            'id', 'vendor_id', 'video_url', 'thumbnail_url',
                            'caption', 'likes_count', 'views_count', 'is_liked',
                            'share_url', 'created_at',
                            'vendor' => ['name', 'profile_image'],
                        ],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_feed_returns_newest_first(): void
    {
        $older = WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subDay(),
        ]);
        $newer = WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos');

        $response->assertOk();
        $videoIds = collect($response->json('data.videos'))->pluck('id')->toArray();
        $this->assertEquals([$newer->id, $older->id], $videoIds);
    }

    public function test_feed_respects_per_page_limit(): void
    {
        WawVideo::factory()->count(5)->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data.videos')
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.last_page', 3);
    }

    public function test_feed_caps_per_page_at_50(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos?per_page=100');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    public function test_feed_shows_is_liked_correctly(): void
    {
        $likedVideo = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);
        $unlikedVideo = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);

        WawVideoLike::create([
            'waw_video_id' => $likedVideo->id,
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos');

        $response->assertOk();
        $videos = collect($response->json('data.videos'));

        $this->assertTrue($videos->firstWhere('id', $likedVideo->id)['is_liked']);
        $this->assertFalse($videos->firstWhere('id', $unlikedVideo->id)['is_liked']);
    }

    public function test_feed_includes_product_and_service(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);
        $service = Service::factory()->create(['vendor_id' => $this->vendor->id]);

        WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $product->id,
        ]);
        WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'service_id' => $service->id,
        ]);
        WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos');

        $response->assertOk();
        $videos = collect($response->json('data.videos'));

        $withProduct = $videos->firstWhere('product.id', $product->id);
        $this->assertNotNull($withProduct);
        $this->assertNotNull($withProduct['product']['name']);

        $withService = $videos->firstWhere('service.id', $service->id);
        $this->assertNotNull($withService);
        $this->assertNotNull($withService['service']['name']);

        $plain = $videos->first(fn ($v) => $v['product'] === null && $v['service'] === null);
        $this->assertNotNull($plain);
    }

    public function test_feed_can_filter_by_vendor_id(): void
    {
        $vendor2 = User::factory()->create(['role' => 'vendor']);

        WawVideo::factory()->count(2)->create(['vendor_id' => $this->vendor->id]);
        WawVideo::factory()->count(3)->create(['vendor_id' => $vendor2->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/waw-videos?vendor_id='.$this->vendor->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data.videos')
            ->assertJsonPath('data.pagination.total', 2);
    }

    // ── Upload Endpoint ────────────────────────────────────────────

    public function test_customer_cannot_upload_video(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'caption' => 'Test caption',
            ])
            ->assertStatus(403);
    }

    public function test_vendor_can_upload_video(): void
    {
        Storage::fake('r2');

        $video = UploadedFile::fake()->create('video.mp4', 30000, 'video/mp4');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'caption' => 'Check out our new collection!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'video' => [
                        'id', 'vendor_id', 'video_url', 'thumbnail_url',
                        'caption', 'likes_count', 'is_liked', 'share_url',
                        'vendor' => ['name', 'profile_image'],
                    ],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.video.caption', 'Check out our new collection!')
            ->assertJsonPath('data.video.likes_count', 0)
            ->assertJsonPath('data.video.is_liked', false);

        $this->assertDatabaseHas('waw_videos', [
            'vendor_id' => $this->vendor->id,
            'caption' => 'Check out our new collection!',
        ]);
    }

    public function test_vendor_can_upload_video_with_thumbnail(): void
    {
        Storage::fake('r2');

        $video = UploadedFile::fake()->create('video.mp4', 30000, 'video/mp4');
        $thumbnail = UploadedFile::fake()->create('thumb.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'thumbnail' => $thumbnail,
                'caption' => 'With thumbnail!',
            ]);

        $response->assertStatus(201);

        $wawVideo = WawVideo::latest()->first();
        $this->assertNotNull($wawVideo->thumbnail_url);
    }

    public function test_vendor_can_tag_product_to_video(): void
    {
        Storage::fake('r2');

        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);
        $video = UploadedFile::fake()->create('video.mp4', 30000, 'video/mp4');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'caption' => 'Product showcase',
                'product_id' => $product->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video.product.id', $product->id);
    }

    public function test_upload_validates_required_fields(): void
    {
        $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video', 'caption']);
    }

    public function test_upload_rejects_oversized_video(): void
    {
        $video = UploadedFile::fake()->create('video.mp4', 60000, 'video/mp4');

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'caption' => 'Too large',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_invalid_video_format(): void
    {
        $video = UploadedFile::fake()->create('video.txt', 1000, 'text/plain');

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'caption' => 'Wrong format',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    public function test_caption_max_length_is_200(): void
    {
        $video = UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4');

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/waw-videos', [
                'video' => $video,
                'caption' => str_repeat('a', 201),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caption']);
    }

    // ── Like Toggle ────────────────────────────────────────────────

    public function test_user_can_like_a_video(): void
    {
        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id, 'likes_count' => 0]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/waw-videos/{$video->id}/like");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_liked', true)
            ->assertJsonPath('likes_count', 1);

        $this->assertDatabaseHas('waw_video_likes', [
            'waw_video_id' => $video->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_user_can_unlike_a_video(): void
    {
        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id, 'likes_count' => 1]);

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/waw-videos/{$video->id}/like");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_liked', false)
            ->assertJsonPath('likes_count', 0);

        $this->assertDatabaseMissing('waw_video_likes', [
            'waw_video_id' => $video->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_like_toggle_on_nonexistent_video_returns_404(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/waw-videos/99999/like')
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_like(): void
    {
        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);

        $this->postJson("/api/v1/waw-videos/{$video->id}/like")
            ->assertStatus(401);
    }

    // ── Delete Endpoint ────────────────────────────────────────────

    public function test_vendor_can_delete_own_video(): void
    {
        Storage::fake('r2');

        $video = WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'video_url' => 'waw-videos/1/test.mp4',
            'thumbnail_url' => 'waw-videos/1/thumbs/test.jpg',
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/waw-videos/{$video->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Video deleted successfully.');

        $this->assertDatabaseMissing('waw_videos', ['id' => $video->id]);
    }

    public function test_vendor_cannot_delete_another_vendors_video(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $video = WawVideo::factory()->create(['vendor_id' => $otherVendor->id]);

        $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/waw-videos/{$video->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('waw_videos', ['id' => $video->id]);
    }

    public function test_customer_cannot_delete_video(): void
    {
        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/waw-videos/{$video->id}")
            ->assertStatus(403);
    }

    public function test_deleting_video_cascades_likes(): void
    {
        Storage::fake('r2');

        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $this->customer->id,
        ]);

        $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson("/api/v1/waw-videos/{$video->id}")
            ->assertOk();

        $this->assertDatabaseMissing('waw_video_likes', ['waw_video_id' => $video->id]);
    }

    public function test_deleting_nonexistent_video_returns_404(): void
    {
        $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson('/api/v1/waw-videos/99999')
            ->assertStatus(404);
    }

    // ── Vendor Videos Endpoint ─────────────────────────────────────

    public function test_vendor_can_list_own_videos(): void
    {
        $otherVendor = User::factory()->create(['role' => 'vendor']);

        WawVideo::factory()->count(3)->create(['vendor_id' => $this->vendor->id]);
        WawVideo::factory()->count(2)->create(['vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/waw-videos');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.videos')
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_customer_cannot_access_vendor_videos_endpoint(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/vendor/waw-videos')
            ->assertStatus(403);
    }

    public function test_vendor_videos_are_newest_first(): void
    {
        $older = WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subDay(),
        ]);
        $newer = WawVideo::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/waw-videos');

        $videoIds = collect($response->json('data.videos'))->pluck('id')->toArray();
        $this->assertEquals([$newer->id, $older->id], $videoIds);
    }

    // ── Deep Link ──────────────────────────────────────────────────

    public function test_share_link_redirects_to_website(): void
    {
        $video = WawVideo::factory()->create(['vendor_id' => $this->vendor->id]);

        $this->get("/waw/{$video->id}")
            ->assertRedirect('https://surprisemoi.com');
    }

    public function test_share_link_returns_404_for_nonexistent_video(): void
    {
        $this->get('/waw/99999')
            ->assertStatus(404);
    }
}
