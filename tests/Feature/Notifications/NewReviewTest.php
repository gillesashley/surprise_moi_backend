<?php

namespace Tests\Feature\Notifications;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\NewReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

class NewReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_is_notified_when_product_is_reviewed(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $reviewer = User::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        Notification::assertSentTo($vendor, NewReview::class, function (NewReview $notification) use ($reviewer) {
            return $notification->reviewer->id === $reviewer->id;
        });
    }

    public function test_notification_is_not_sent_when_vendor_reviews_own_product(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        Review::factory()->forProduct($product)->create([
            'user_id' => $vendor->id,
        ]);

        Notification::assertNotSentTo($vendor, NewReview::class);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $reviewer = User::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $review = Review::factory()->forProduct($product)->withRating(4.5)->create([
            'user_id' => $reviewer->id,
        ]);

        $notification = new NewReview($reviewer, $review);
        $data = $notification->toDatabase($vendor);

        $this->assertSame('new_review', $data['type']);
        $this->assertSame('New Review', $data['title']);
        $this->assertSame("{$reviewer->name} left a {$review->rating}-star review on your product", $data['message']);
        $this->assertSame("/reviews/{$review->id}", $data['action_url']);

        $this->assertArrayHasKey('actor', $data);
        $this->assertSame($reviewer->id, $data['actor']['id']);
        $this->assertSame($reviewer->name, $data['actor']['name']);
        $this->assertSame($reviewer->avatar, $data['actor']['avatar']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($review->id, $data['subject']['id']);
        $this->assertSame('review', $data['subject']['type']);
        $this->assertSame($review->rating, $data['subject']['rating']);
        $this->assertSame('product', $data['subject']['reviewable_type']);
        $this->assertSame($review->reviewable_id, $data['subject']['reviewable_id']);
    }

    public function test_to_fcm_returns_correct_message(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $reviewer = User::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $review = Review::factory()->forProduct($product)->create([
            'user_id' => $reviewer->id,
        ]);

        $notification = new NewReview($reviewer, $review);
        $fcmMessage = $notification->toFcm($vendor);

        $this->assertInstanceOf(FcmMessage::class, $fcmMessage);
    }
}
