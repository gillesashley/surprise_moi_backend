<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\WawVideo;
use App\Models\WawVideoLike;
use App\Notifications\WawVideoLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

class WawVideoLikedTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_sent_when_video_is_liked(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $liker = User::factory()->create();
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $liker->id,
        ]);

        Notification::assertSentTo($vendor, WawVideoLiked::class, function (WawVideoLiked $notification) use ($liker, $video) {
            return $notification->liker->id === $liker->id
                && $notification->video->id === $video->id;
        });
    }

    public function test_notification_is_not_sent_when_owner_likes_own_video(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $vendor->id,
        ]);

        Notification::assertNotSentTo($vendor, WawVideoLiked::class);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $liker = User::factory()->create();
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        $notification = new WawVideoLiked($liker, $video);
        $data = $notification->toDatabase($vendor);

        $this->assertSame('waw_video_liked', $data['type']);
        $this->assertSame('Someone liked your video', $data['title']);
        $this->assertSame("{$liker->name} liked your video", $data['message']);
        $this->assertSame("/waw/videos/{$video->id}", $data['action_url']);

        $this->assertArrayHasKey('actor', $data);
        $this->assertSame($liker->id, $data['actor']['id']);
        $this->assertSame($liker->name, $data['actor']['name']);
        $this->assertSame($liker->avatar, $data['actor']['avatar']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($video->id, $data['subject']['id']);
        $this->assertSame('waw_video', $data['subject']['type']);
    }

    public function test_to_fcm_returns_correct_message(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $liker = User::factory()->create();
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        $notification = new WawVideoLiked($liker, $video);
        $fcmMessage = $notification->toFcm($vendor);

        $this->assertInstanceOf(FcmMessage::class, $fcmMessage);
    }
}
