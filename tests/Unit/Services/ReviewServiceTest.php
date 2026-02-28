<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_integer_rating_distribution(): void
    {
        $service = app(ReviewService::class);

        $distribution = $service->calculateRatingDistribution([
            '1.0' => 2,
            '1.5' => 1,
            '3.0' => 4,
            '4.5' => 3,
            '5.0' => 1,
        ]);

        $this->assertSame([
            '1' => 2,
            '2' => 1,
            '3' => 4,
            '4' => 0,
            '5' => 4,
        ], $distribution);
    }

    public function test_it_checks_verified_purchase_from_eligible_order_context(): void
    {
        $service = app(ReviewService::class);
        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => 'delivered',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
        ]);

        $this->assertTrue($service->isVerifiedPurchase(
            $customer->id,
            'product',
            $product->id,
            $order->id
        ));

        $this->assertFalse($service->isVerifiedPurchase(
            $customer->id,
            'product',
            $product->id,
            999999
        ));
    }

    public function test_it_updates_item_aggregates_after_reviews_change(): void
    {
        $service = app(ReviewService::class);
        $product = Product::factory()->create([
            'rating' => 0,
            'reviews_count' => 0,
        ]);

        Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 4.0,
        ]);

        Review::factory()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 5.0,
        ]);

        $service->updateReviewableRating($product);
        $product->refresh();

        $this->assertSame(2, $product->reviews_count);
        $this->assertSame(4.5, (float) $product->rating);
    }
}
