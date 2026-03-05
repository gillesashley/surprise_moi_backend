<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSpecialOfferTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create();
        $this->shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);
    }

    public function test_lists_only_current_active_offers(): void
    {
        $product1 = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);
        $product2 = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);
        $product3 = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);
        $product4 = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);

        // Current active offer - should appear
        $activeOffer = SpecialOffer::factory()->create(['product_id' => $product1->id]);

        // Expired offer - should NOT appear
        SpecialOffer::factory()->expired()->create(['product_id' => $product2->id]);

        // Future offer - should NOT appear
        SpecialOffer::factory()->future()->create(['product_id' => $product3->id]);

        // Inactive offer - should NOT appear
        SpecialOffer::factory()->inactive()->create(['product_id' => $product4->id]);

        $response = $this->getJson('/api/v1/special-offers');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeOffer->id);
    }

    public function test_returns_correct_response_structure(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'name' => 'Gift Hamper Deluxe',
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 20,
            'tag' => SpecialOffer::TAG_TODAYS_OFFERS,
        ]);

        $response = $this->getJson('/api/v1/special-offers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'discount_percentage',
                        'tag',
                        'starts_at',
                        'ends_at',
                        'is_active',
                        'product' => [
                            'id',
                            'name',
                            'price',
                            'discounted_price',
                            'thumbnail',
                            'images',
                            'shop' => ['id', 'name'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_computes_discounted_price_correctly(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 150.00,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 20,
        ]);

        $response = $this->getJson('/api/v1/special-offers');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.product.price', 150.0)
            ->assertJsonPath('data.0.product.discounted_price', 120.0);
    }

    public function test_paginates_results(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $product = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);
            SpecialOffer::factory()->create(['product_id' => $product->id]);
        }

        $response = $this->getJson('/api/v1/special-offers');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 20);
    }
}
