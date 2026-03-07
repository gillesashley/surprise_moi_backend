<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSpecialOfferTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $vendor;

    protected Shop $shop;

    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->vendor = User::factory()->vendor()->create();
        $this->shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);
        $this->address = Address::factory()->create(['user_id' => $this->customer->id]);
    }

    public function test_order_without_cart_uses_special_offer_price(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 40,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [[
                    'orderable_type' => 'product',
                    'orderable_id' => $product->id,
                    'quantity' => 1,
                ]],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(201);
        $order = Order::first();
        // 200 * 0.60 = 120.00
        $this->assertEquals(120.00, (float) $order->subtotal);
        $this->assertEquals(120.00, (float) $order->total);
    }

    public function test_order_with_cart_uses_special_offer_price(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 30,
        ]);

        // Simulate cart with correct special offer price
        $cart = Cart::create(['user_id' => $this->customer->id, 'currency' => 'GHS']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $product->name,
            'unit_price_cents' => 7000, // 100 * 0.70 = 70.00
            'quantity' => 2,
        ]);
        $cart->recalculateTotals();
        $cart->save();

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [[
                    'orderable_type' => 'product',
                    'orderable_id' => $product->id,
                    'quantity' => 2,
                ]],
                'delivery_address_id' => $this->address->id,
            ]);

        $response->assertStatus(201);
        $order = Order::first();
        // 70.00 * 2 = 140.00
        $this->assertEquals(140.00, (float) $order->subtotal);
    }

    public function test_order_detects_price_change_when_offer_started_after_cart(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 100.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 10,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        // Cart was created BEFORE special offer (full price)
        $cart = Cart::create(['user_id' => $this->customer->id, 'currency' => 'GHS']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $product->name,
            'unit_price_cents' => 10000, // Full price: 100.00
            'quantity' => 1,
        ]);
        $cart->recalculateTotals();
        $cart->save();

        // Special offer created AFTER cart — price dropped
        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 30,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [[
                    'orderable_type' => 'product',
                    'orderable_id' => $product->id,
                    'quantity' => 1,
                ]],
                'delivery_address_id' => $this->address->id,
            ]);

        // Should detect price mismatch (cart: 100, current effective: 70)
        $response->assertStatus(409)
            ->assertJsonFragment(['code' => 'price_changed']);
    }

    public function test_full_flow_special_offer_cart_to_order(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 150.00,
            'discount_price' => null,
            'is_available' => true,
            'stock' => 5,
            'delivery_fee' => 0,
            'free_delivery' => true,
        ]);

        SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'discount_percentage' => 20,
        ]);

        // Step 1: Add to cart via API (should use effective price)
        $cartResponse = $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $cartResponse->assertStatus(201);
        $this->assertEquals(12000, $cartResponse->json('data.item.unit_price_cents'));

        // Step 2: Create order (should match cart price)
        $orderResponse = $this->actingAs($this->customer)
            ->postJson('/api/v1/orders', [
                'items' => [[
                    'orderable_type' => 'product',
                    'orderable_id' => $product->id,
                    'quantity' => 2,
                ]],
                'delivery_address_id' => $this->address->id,
            ]);

        $orderResponse->assertStatus(201);
        $order = Order::first();
        // 150 * 0.80 = 120.00 per unit * 2 = 240.00
        $this->assertEquals(240.00, (float) $order->subtotal);
        $this->assertEquals(240.00, (float) $order->total);

        // Step 3: Verify stock was decremented
        $this->assertEquals(3, $product->fresh()->stock);
    }
}
