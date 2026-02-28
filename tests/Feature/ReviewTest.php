<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    public function test_user_can_create_review_with_mobile_contract(): void
    {
        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $order = $this->createEligibleOrderWithItem($customer, $vendor, $product);

        $response = $this->actingAs($customer)->post('/api/v1/reviews', [
            'item_id' => $product->id,
            'item_type' => 'product',
            'order_id' => $order->id,
            'rating' => 4.5,
            'comment' => 'Excellent gift quality.',
            'images' => [
                UploadedFile::fake()->create('review-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('review-2.png', 100, 'image/png'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'data' => [
                    'review' => [
                        'item_id' => $product->id,
                        'item_type' => 'product',
                        'rating' => 4.5,
                        'is_verified_purchase' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'item_id' => $product->id,
            'item_type' => 'product',
            'reviewable_id' => $product->id,
            'reviewable_type' => 'product',
            'order_id' => $order->id,
            'rating' => 4.5,
            'is_verified_purchase' => true,
        ]);

        $product->refresh();
        $this->assertSame(4.5, (float) $product->rating);
        $this->assertSame(1, $product->reviews_count);
    }

    public function test_user_cannot_review_item_without_eligible_purchase(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($customer)->postJson('/api/v1/reviews', [
            'item_id' => $product->id,
            'item_type' => 'product',
            'rating' => 5,
            'comment' => 'No purchase made.',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You can only review purchased items from completed orders.',
            ]);
    }

    public function test_user_cannot_submit_duplicate_review_for_same_order_context(): void
    {
        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $service = Service::factory()->create(['vendor_id' => $vendor->id]);
        $order = $this->createEligibleOrderWithItem($customer, $vendor, $service);

        $payload = [
            'item_id' => $service->id,
            'item_type' => 'service',
            'order_id' => $order->id,
            'rating' => 4.0,
            'comment' => 'Great booking experience.',
        ];

        $this->actingAs($customer)->postJson('/api/v1/reviews', $payload)->assertStatus(201);

        $response = $this->actingAs($customer)->postJson('/api/v1/reviews', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have already reviewed this purchase context.',
            ]);
    }

    public function test_same_item_can_be_reviewed_for_different_orders(): void
    {
        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $orderOne = $this->createEligibleOrderWithItem($customer, $vendor, $product);
        $orderTwo = $this->createEligibleOrderWithItem($customer, $vendor, $product);

        $this->actingAs($customer)->postJson('/api/v1/reviews', [
            'item_id' => $product->id,
            'item_type' => 'product',
            'order_id' => $orderOne->id,
            'rating' => 4.5,
        ])->assertStatus(201);

        $this->actingAs($customer)->postJson('/api/v1/reviews', [
            'item_id' => $product->id,
            'item_type' => 'product',
            'order_id' => $orderTwo->id,
            'rating' => 5.0,
        ])->assertStatus(201);

        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_reviews_index_is_public_and_item_scoped(): void
    {
        $product = Product::factory()->create();

        Review::factory()->count(3)->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/reviews?item_id={$product->id}&item_type=product");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'user_id',
                            'user_name',
                            'user_avatar',
                            'item_name',
                            'item_id',
                            'item_type',
                            'order_id',
                            'rating',
                            'comment',
                            'images',
                            'helpful_count',
                            'is_helpful_by_me',
                            'is_verified_purchase',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'average_rating',
                    'total_reviews',
                    'rating_distribution' => [
                        '1',
                        '2',
                        '3',
                        '4',
                        '5',
                    ],
                    'current_page',
                    'last_page',
                ],
            ]);

        $this->assertSame(3, $response->json('data.total_reviews'));
    }

    public function test_reviews_index_requires_item_filters(): void
    {
        $response = $this->getJson('/api/v1/reviews');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['item_id', 'item_type']);
    }

    public function test_review_show_is_public(): void
    {
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review retrieved successfully.',
                'data' => [
                    'id' => $review->id,
                ],
            ]);
    }

    public function test_user_can_update_own_review_with_matching_item_payload(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $customer->id,
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 3.0,
            'comment' => 'Old comment.',
        ]);

        $response = $this->actingAs($customer)->putJson("/api/v1/reviews/{$review->id}", [
            'item_id' => $product->id,
            'item_type' => 'product',
            'rating' => 4.5,
            'comment' => 'Updated after second delivery.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review updated successfully.',
                'data' => [
                    'review' => [
                        'rating' => 4.5,
                        'comment' => 'Updated after second delivery.',
                    ],
                ],
            ]);
    }

    public function test_user_cannot_update_review_with_mismatched_item_context(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $anotherProduct = Product::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $customer->id,
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $response = $this->actingAs($customer)->putJson("/api/v1/reviews/{$review->id}", [
            'item_id' => $anotherProduct->id,
            'item_type' => 'product',
            'rating' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Item context does not match this review.',
            ]);
    }

    public function test_user_can_delete_own_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $customer->id,
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $this->actingAs($customer)
            ->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Review deleted successfully.',
            ]);

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_helpful_endpoint_toggles_for_authenticated_user(): void
    {
        $product = Product::factory()->create();

        $review = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $helper = User::factory()->create();

        $first = $this->actingAs($helper)->postJson("/api/v1/reviews/{$review->id}/helpful");
        $first->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_helpful_by_me' => true,
                    'helpful_count' => 1,
                ],
            ]);

        $second = $this->actingAs($helper)->postJson("/api/v1/reviews/{$review->id}/helpful");
        $second->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_helpful_by_me' => false,
                    'helpful_count' => 0,
                ],
            ]);
    }

    public function test_vendor_can_list_only_their_reviews_with_filters(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $otherVendor = User::factory()->create(['role' => 'vendor']);

        $ownedProduct = Product::factory()->create(['vendor_id' => $vendor->id]);
        $otherProduct = Product::factory()->create(['vendor_id' => $otherVendor->id]);

        $matching = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $ownedProduct->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $ownedProduct->id,
            'rating' => 5.0,
            'comment' => 'Amazing quality and packaging.',
            'images' => ['reviews/owned.jpg'],
        ]);
        $matching->reviewImages()->create([
            'storage_path' => 'reviews/owned.jpg',
            'sort_order' => 1,
        ]);

        Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $ownedProduct->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $ownedProduct->id,
            'rating' => 3.0,
            'comment' => 'Average result.',
            'images' => null,
        ]);

        Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $otherProduct->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $otherProduct->id,
            'rating' => 5.0,
            'comment' => 'Amazing but not mine.',
            'images' => ['reviews/other.jpg'],
        ]);

        $response = $this->actingAs($vendor)
            ->getJson('/api/v1/vendor/reviews?rating=5&has_images=1&search=Amazing');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reviews',
                    'average_rating',
                    'total_reviews',
                    'rating_distribution' => ['1', '2', '3', '4', '5'],
                    'current_page',
                    'last_page',
                ],
            ]);

        $this->assertSame(1, $response->json('data.total_reviews'));
        $this->assertSame($matching->id, $response->json('data.reviews.0.id'));
    }

    public function test_non_vendor_cannot_access_vendor_reviews_endpoint(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->getJson('/api/v1/vendor/reviews')
            ->assertStatus(403);
    }

    public function test_vendor_can_create_and_upsert_single_reply_for_owned_review(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $review = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $create = $this->actingAs($vendor)->postJson("/api/v1/reviews/{$review->id}/replies", [
            'message' => 'Thanks for your feedback.',
        ]);

        $create->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Thanks for your feedback.',
                ],
            ]);

        $update = $this->actingAs($vendor)->postJson("/api/v1/reviews/{$review->id}/replies", [
            'message' => 'Thanks again, we improved packaging.',
        ]);

        $update->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Thanks again, we improved packaging.',
                ],
            ]);

        $this->assertDatabaseCount('review_replies', 1);
    }

    public function test_vendor_reply_endpoints_respect_ownership(): void
    {
        $ownerVendor = User::factory()->create(['role' => 'vendor']);
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $ownerVendor->id]);

        $review = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        $this->actingAs($otherVendor)->postJson("/api/v1/reviews/{$review->id}/replies", [
            'message' => 'Not my review.',
        ])->assertStatus(403);

        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $ownerVendor->id,
        ]);

        $this->actingAs($otherVendor)->putJson("/api/v1/review-replies/{$reply->id}", [
            'message' => 'Trying to overwrite.',
        ])->assertStatus(403);

        $this->actingAs($otherVendor)
            ->deleteJson("/api/v1/review-replies/{$reply->id}")
            ->assertStatus(403);
    }

    public function test_review_replies_can_be_listed_publicly(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $review = Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $vendor->id,
            'message' => 'We appreciate your order.',
        ]);

        $response = $this->getJson("/api/v1/reviews/{$review->id}/replies");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }

    private function createEligibleOrderWithItem(
        User $customer,
        User $vendor,
        Product|Service $item,
        string $status = 'delivered'
    ): Order {
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => $status,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => $item::class,
            'orderable_id' => $item->id,
        ]);

        return $order;
    }
}
