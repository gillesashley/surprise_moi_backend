<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_reviews(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Review::factory()->count(3)->create([
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        // Create reviews by another user (should not be visible)
        Review::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/reviews');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'rating',
                        'comment',
                        'is_verified_purchase',
                        'user',
                        'reviewable_type',
                        'reviewable_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_user_can_create_product_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 5,
                'comment' => 'Excellent product! Highly recommended.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Review submitted successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'rating',
                    'comment',
                    'is_verified_purchase',
                    'user',
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 5,
        ]);
    }

    public function test_user_can_create_service_review(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'service',
                'reviewable_id' => $service->id,
                'rating' => 4,
                'comment' => 'Great service experience!',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'reviewable_type' => Service::class,
            'reviewable_id' => $service->id,
            'rating' => 4,
        ]);
    }

    public function test_user_cannot_review_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 3,
                'comment' => 'Another review attempt.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have already reviewed this product.',
            ]);
    }

    public function test_verified_purchase_is_detected(): void
    {
        $user = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        // Create a delivered order with this product
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 5,
                'comment' => 'Verified purchase review.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'is_verified_purchase' => true,
                ],
            ]);
    }

    public function test_non_verified_purchase_is_marked_correctly(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // User hasn't purchased this product
        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 4,
                'comment' => 'Non-verified review.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'is_verified_purchase' => false,
                ],
            ]);
    }

    public function test_user_can_update_their_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 3,
            'comment' => 'Original comment.',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 5,
                'comment' => 'Updated comment after better experience.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review updated successfully.',
            ]);

        $review->refresh();
        $this->assertEquals(5, $review->rating);
        $this->assertEquals('Updated comment after better experience.', $review->comment);
    }

    public function test_user_cannot_update_another_users_review(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $otherUser->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 1,
                'comment' => 'Trying to change someone else\'s review.',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review deleted successfully.',
            ]);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_user_cannot_delete_another_users_review(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $otherUser->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_product_rating_is_updated_after_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'rating' => 0,
            'reviews_count' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 4,
                'comment' => 'Great product!',
            ]);

        $product->refresh();
        $this->assertEquals(4.0, (float) $product->rating);
        $this->assertEquals(1, $product->reviews_count);
    }

    public function test_can_view_product_reviews_publicly(): void
    {
        $product = Product::factory()->create();
        Review::factory()->count(5)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'rating', 'comment', 'user'],
                ],
                'stats' => [
                    'average_rating',
                    'total_reviews',
                    'verified_purchases',
                    'rating_distribution',
                ],
                'meta',
            ]);
    }

    public function test_can_view_service_reviews_publicly(): void
    {
        $service = Service::factory()->create();
        Review::factory()->count(3)->create([
            'reviewable_type' => Service::class,
            'reviewable_id' => $service->id,
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'stats',
                'meta',
            ]);
    }

    public function test_can_view_vendor_reviews_publicly(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $service = Service::factory()->create(['vendor_id' => $vendor->id]);

        Review::factory()->count(3)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        Review::factory()->count(2)->create([
            'reviewable_type' => Service::class,
            'reviewable_id' => $service->id,
        ]);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'stats' => [
                    'average_rating',
                    'total_reviews',
                    'verified_purchases',
                    'rating_distribution',
                ],
                'meta',
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_can_filter_reviews_by_rating(): void
    {
        $product = Product::factory()->create();

        Review::factory()->count(3)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 5,
        ]);

        Review::factory()->count(2)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 3,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews?rating=5");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_can_filter_verified_reviews_only(): void
    {
        $product = Product::factory()->create();

        Review::factory()->count(3)->verified()->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        Review::factory()->count(2)->unverified()->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews?verified_only=true");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_review_validation_requires_rating(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'comment' => 'Missing rating.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_validation_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_can_have_images(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => $product->id,
                'rating' => 5,
                'comment' => 'With images!',
                'images' => [
                    'reviews/image1.jpg',
                    'reviews/image2.jpg',
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
        ]);

        $review = Review::where('user_id', $user->id)->first();
        $this->assertCount(2, $review->images);
    }

    public function test_review_for_nonexistent_product_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'product',
                'reviewable_id' => 99999,
                'rating' => 5,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found.',
            ]);
    }

    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_rating_distribution_is_correct(): void
    {
        $product = Product::factory()->create();

        // Create reviews with specific ratings
        Review::factory()->count(5)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 5,
        ]);

        Review::factory()->count(3)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 4,
        ]);

        Review::factory()->count(2)->create([
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'rating' => 3,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertStatus(200);

        $distribution = $response->json('stats.rating_distribution');
        $this->assertEquals(5, $distribution[5]);
        $this->assertEquals(3, $distribution[4]);
        $this->assertEquals(2, $distribution[3]);
        $this->assertEquals(0, $distribution[2]);
        $this->assertEquals(0, $distribution[1]);
    }
}
