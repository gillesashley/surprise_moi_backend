<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSpecialOfferDisplayTest extends TestCase
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

    public function test_product_detail_shows_special_offer_discount(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 150.00,
            'discount_price' => null,
            'discount_percentage' => null,
            'is_available' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 20,
            'tag' => SpecialOffer::TAG_LIMITED_TIME,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $product = $response->json('data.product');
        $this->assertEquals(150.0, $product['price']);
        $this->assertEquals(120.0, $product['discount_price']);
        $this->assertEquals(20, $product['discount_percentage']);
    }

    public function test_product_detail_includes_active_offer_object(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'is_available' => true,
        ]);

        $offer = SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 30,
            'tag' => SpecialOffer::TAG_FLASH_SALE,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'product' => [
                        'active_offer' => [
                            'id',
                            'discount_percentage',
                            'tag',
                            'starts_at',
                            'ends_at',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.product.active_offer.id', $offer->id)
            ->assertJsonPath('data.product.active_offer.tag', 'Flash Sale');
    }

    public function test_product_detail_active_offer_null_when_no_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.product.active_offer', null);
    }

    public function test_product_detail_uses_vendor_discount_when_no_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $product = $response->json('data.product');
        $this->assertEquals(80.0, $product['discount_price']);
        $this->assertEquals(20, $product['discount_percentage']);
        $this->assertNull($product['active_offer']);
    }

    public function test_product_listing_shows_special_offer_discount(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => null,
            'discount_percentage' => null,
            'is_available' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 25,
            'tag' => SpecialOffer::TAG_SPECIAL_OFFERS,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $products = $response->json('data.products');
        $found = collect($products)->firstWhere('id', $product->id);

        $this->assertNotNull($found);
        $this->assertEquals(200.0, $found['price']);
        $this->assertEquals(150.0, $found['discount_price']);
        $this->assertEquals(25, $found['discount_percentage']);
    }

    public function test_product_by_slug_shows_special_offer_discount(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 50,
        ]);

        $response = $this->getJson("/api/v1/products/by-slug/{$product->slug}");

        $response->assertStatus(200);
        $product = $response->json('data.product');
        $this->assertEquals(50.0, $product['discount_price']);
        $this->assertEquals(50, $product['discount_percentage']);
    }
}
