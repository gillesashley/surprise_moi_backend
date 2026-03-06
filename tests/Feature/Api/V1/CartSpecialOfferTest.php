<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartSpecialOfferTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $vendor;

    protected Shop $shop;

    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->vendor = User::factory()->vendor()->create();
        $this->shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);
        $this->cartService = app(CartService::class);
    }

    public function test_add_to_cart_uses_special_offer_price(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 40,
        ]);

        $cart = $this->cartService->getOrCreateCart($this->customer);
        $cartItem = $this->cartService->addItem($cart, [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // 100 * 0.60 = 60.00 = 6000 cents
        $this->assertEquals(6000, $cartItem->unit_price_cents);
    }

    public function test_add_to_cart_uses_base_price_when_no_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
        ]);

        $cart = $this->cartService->getOrCreateCart($this->customer);
        $cartItem = $this->cartService->addItem($cart, [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(10000, $cartItem->unit_price_cents);
    }

    public function test_add_to_cart_uses_vendor_discount_when_no_offer(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => 85.00,
            'is_available' => true,
            'stock' => 10,
        ]);

        $cart = $this->cartService->getOrCreateCart($this->customer);
        $cartItem = $this->cartService->addItem($cart, [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(8500, $cartItem->unit_price_cents);
    }

    public function test_add_to_cart_via_api_uses_special_offer_price(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 25,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201);
        // 200 * 0.75 = 150.00 = 15000 cents per unit, 2 units = 30000
        $this->assertEquals(15000, $response->json('data.item.unit_price_cents'));
        $this->assertEquals(30000, $response->json('data.cart.total_cents'));
    }
}
