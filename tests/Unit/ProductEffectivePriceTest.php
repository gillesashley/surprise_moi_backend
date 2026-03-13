<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEffectivePriceTest extends TestCase
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

    public function test_effective_price_returns_base_price_when_no_discount(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
        ]);

        $this->assertEquals(100.00, $product->effective_price);
    }

    public function test_effective_price_returns_vendor_discount_price_when_set(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => 75.00,
        ]);

        $this->assertEquals(75.00, $product->effective_price);
    }

    public function test_effective_price_uses_special_offer_when_active(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => 180.00,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 40,
        ]);

        $loaded = Product::with('activeOffer')->find($product->id);
        $this->assertEquals(120.00, $loaded->effective_price);
    }

    public function test_effective_price_ignores_expired_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => 180.00,
        ]);

        SpecialOffer::factory()->expired()->create([
            'product_id' => $product->id,
            'discount_percentage' => 40,
        ]);

        $this->assertEquals(180.00, $product->fresh()->effective_price);
    }

    public function test_effective_price_ignores_inactive_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => null,
        ]);

        SpecialOffer::factory()->inactive()->create([
            'product_id' => $product->id,
            'discount_percentage' => 25,
        ]);

        $this->assertEquals(200.00, $product->fresh()->effective_price);
    }

    public function test_effective_price_ignores_future_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => null,
        ]);

        SpecialOffer::factory()->future()->create([
            'product_id' => $product->id,
            'discount_percentage' => 30,
        ]);

        $this->assertEquals(200.00, $product->fresh()->effective_price);
    }

    public function test_effective_discount_percentage_from_special_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_percentage' => 10,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 40,
        ]);

        $loaded = Product::with('activeOffer')->find($product->id);
        $this->assertEquals(40, $loaded->effective_discount_percentage);
    }

    public function test_effective_discount_percentage_falls_back_to_vendor(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_percentage' => 10,
        ]);

        $this->assertEquals(10, $product->effective_discount_percentage);
    }

    public function test_effective_price_works_with_eager_loaded_active_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 25,
        ]);

        $loaded = Product::with('activeOffer')->find($product->id);
        $this->assertEquals(75.00, $loaded->effective_price);
    }
}
