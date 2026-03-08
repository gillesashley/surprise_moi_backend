<?php

namespace Tests\Feature\Notifications;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Notifications\ReviewReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

class ReviewRepliedTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_author_is_notified_when_vendor_replies(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $review = Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $vendor->id,
        ]);

        Notification::assertSentTo($reviewer, ReviewReplied::class, function (ReviewReplied $notification) use ($vendor) {
            return $notification->replier->id === $vendor->id;
        });
    }

    public function test_notification_is_not_sent_when_author_replies_to_own_review(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $reviewer->id]);

        $review = Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $reviewer->id,
        ]);

        Notification::assertNotSentTo($reviewer, ReviewReplied::class);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $review = Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $vendor->id,
        ]);

        $notification = new ReviewReplied($vendor, $reply);
        $data = $notification->toDatabase($reviewer);

        $this->assertSame('review_replied', $data['type']);
        $this->assertSame('New Reply to Your Review', $data['title']);
        $this->assertSame("{$vendor->name} replied to your review", $data['message']);
        $this->assertSame("/reviews/{$review->id}", $data['action_url']);

        $this->assertArrayHasKey('actor', $data);
        $this->assertSame($vendor->id, $data['actor']['id']);
        $this->assertSame($vendor->name, $data['actor']['name']);
        $this->assertSame($vendor->avatar, $data['actor']['avatar']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($reply->id, $data['subject']['id']);
        $this->assertSame('review_reply', $data['subject']['type']);
        $this->assertSame($review->id, $data['subject']['review_id']);
    }

    public function test_to_fcm_returns_correct_message(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $review = Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $vendor->id,
        ]);

        $notification = new ReviewReplied($vendor, $reply);
        $fcmMessage = $notification->toFcm($reviewer);

        $this->assertInstanceOf(FcmMessage::class, $fcmMessage);
    }
}
