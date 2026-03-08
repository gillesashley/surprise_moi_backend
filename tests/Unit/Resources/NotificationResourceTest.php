<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transforms_notification_with_full_data(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'waw_video_liked',
                'title' => 'Video Liked',
                'message' => 'Someone liked your video',
                'action_url' => '/videos/1',
                'actor' => ['id' => 1, 'name' => 'John'],
            ],
            'read_at' => null,
        ]);

        $resource = new NotificationResource($notification);
        $result = $resource->toArray(app(Request::class));

        $this->assertEquals($notification->id, $result['id']);
        $this->assertEquals('waw_video_liked', $result['type']);
        $this->assertEquals('Video Liked', $result['title']);
        $this->assertEquals('Someone liked your video', $result['message']);
        $this->assertEquals('/videos/1', $result['action_url']);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $result['actor']);
        $this->assertNull($result['read_at']);
        $this->assertNotNull($result['created_at']);
        $this->assertNotNull($result['updated_at']);
    }

    public function test_falls_back_to_class_basename_for_type(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\WawVideoLiked',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Video Liked',
                'message' => 'Someone liked your video',
            ],
            'read_at' => now(),
        ]);

        $resource = new NotificationResource($notification);
        $result = $resource->toArray(app(Request::class));

        $this->assertEquals('waw_video_liked', $result['type']);
        $this->assertEquals('Video Liked', $result['title']);
        $this->assertEquals('Someone liked your video', $result['message']);
        $this->assertNull($result['action_url']);
        $this->assertNull($result['actor']);
        $this->assertNotNull($result['read_at']);
    }
}
