# Special Offer Price Integration

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make special offer discounts flow through the entire purchase pipeline — product detail, cart, order, and payment.

**Architecture:** Add `effective_price` and `effective_discount_percentage` accessors on the Product model that consider active special offers. Use these in ProductResource, ProductDetailResource, CartService, and OrderController so the discount is applied everywhere automatically. Expose `active_offer` in product API responses so the Flutter app can show offer tags and countdown timers.

**Tech Stack:** Laravel 12, PHP 8.2, PostgreSQL, PHPUnit

---

## Root Cause

Special offers are display-only. The `SpecialOfferResource` calculates `discounted_price` on-the-fly, but:
- `GET /products/{id}` returns vendor-set `discount_price` (unrelated to special offers)
- `CartService::addItem` snapshots `$product->price * 100` (ignores special offers)
- `OrderController::store` uses `$orderable->discount_price ?? $orderable->price` (ignores special offers)

Result: Customer sees "40% off" on special offers screen, but pays full price.

## Priority Rules

When resolving the effective price for a product:
1. **Active special offer** takes priority (vendor explicitly created a time-limited promotion)
2. **Vendor-set `discount_price`** is fallback (permanent/manual discount)
3. **Base `price`** is final fallback

---

## Tasks

### Task 1: Add `effective_price` accessor to Product model

**Files:**
- Modify: `app/Models/Product.php`
- Create: `tests/Unit/ProductEffectivePriceTest.php`

**Step 1: Write failing tests**

Create `tests/Unit/ProductEffectivePriceTest.php`:

```php
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

        // 200 * (1 - 40/100) = 120.00 — special offer overrides vendor discount
        $this->assertEquals(120.00, $product->fresh()->effective_price);
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

        // Expired offer ignored, falls back to vendor discount_price
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

        $this->assertEquals(40, $product->fresh()->effective_discount_percentage);
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
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/ProductEffectivePriceTest.php`
Expected: FAIL — `effective_price` property doesn't exist

**Step 3: Implement the accessors on Product model**

Add to `app/Models/Product.php` after the `activeOffer()` relationship:

```php
/**
 * Get the effective selling price considering active special offers.
 *
 * Priority: active special offer > vendor discount_price > base price.
 */
public function getEffectivePriceAttribute(): float
{
    $offer = $this->relationLoaded('activeOffer')
        ? $this->activeOffer
        : $this->activeOffer()->first();

    if ($offer) {
        return round((float) $this->price * (1 - $offer->discount_percentage / 100), 2);
    }

    return (float) ($this->discount_price ?? $this->price);
}

/**
 * Get the effective discount percentage considering active special offers.
 */
public function getEffectiveDiscountPercentageAttribute(): ?int
{
    $offer = $this->relationLoaded('activeOffer')
        ? $this->activeOffer
        : $this->activeOffer()->first();

    if ($offer) {
        return $offer->discount_percentage;
    }

    return $this->discount_percentage;
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/ProductEffectivePriceTest.php`
Expected: All 9 PASS

**Step 5: Commit**

```bash
git add app/Models/Product.php tests/Unit/ProductEffectivePriceTest.php
git commit -m "feat: add effective_price accessor to Product model

Resolves the effective selling price by checking for active special
offers first, then falling back to vendor discount_price, then base
price. This ensures special offer discounts flow through everywhere."
```

---

### Task 2: Expose special offer info in ProductResource and ProductDetailResource

**Files:**
- Modify: `app/Http/Resources/ProductResource.php`
- Modify: `app/Http/Resources/ProductDetailResource.php`
- Modify: `app/Http/Controllers/Api/V1/ProductController.php`
- Create: `tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php`

**Step 1: Write failing tests**

Create `tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php`:

```php
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

        $response->assertStatus(200)
            ->assertJsonPath('data.product.price', 150.0)
            ->assertJsonPath('data.product.discount_price', 120.0)
            ->assertJsonPath('data.product.discount_percentage', 20);
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

        $response->assertStatus(200)
            ->assertJsonPath('data.product.discount_price', 80.0)
            ->assertJsonPath('data.product.discount_percentage', 20)
            ->assertJsonPath('data.product.active_offer', null);
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

        $response->assertStatus(200)
            ->assertJsonPath('data.product.discount_price', 50.0)
            ->assertJsonPath('data.product.discount_percentage', 50);
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php`
Expected: FAIL — no `active_offer` field, discount_price not reflecting special offer

**Step 3: Modify ProductController to eager-load `activeOffer`**

In `app/Http/Controllers/Api/V1/ProductController.php`:

**`index()` method** — add `'activeOffer'` to the `with()` array:
```php
$query = Product::query()
    ->with(['category', 'vendor', 'shop', 'images', 'tags', 'activeOffer'])
    ->where('is_available', true);
```

**`show()` method** — add `'activeOffer'` to the `with()` array:
```php
$product = Product::with([
    'category', 'vendor', 'shop', 'images', 'variants', 'tags', 'activeOffer',
])->findOrFail($id);
```

**`showBySlug()` method** — add `'activeOffer'` to the `with()` array:
```php
$product = Product::with([
    'category', 'vendor', 'shop', 'images', 'variants', 'tags', 'activeOffer',
])->where('slug', $slug)->firstOrFail();
```

**Step 4: Modify ProductResource**

In `app/Http/Resources/ProductResource.php`, change the `toArray()` method.

Replace:
```php
'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
'discount_percentage' => $this->discount_percentage,
```

With:
```php
'discount_price' => $this->effective_price < (float) $this->price
    ? $this->effective_price
    : ($this->discount_price ? (float) $this->discount_price : null),
'discount_percentage' => $this->effective_discount_percentage,
'active_offer' => $this->whenLoaded('activeOffer', function () {
    if (! $this->activeOffer) {
        return null;
    }

    return [
        'id' => $this->activeOffer->id,
        'discount_percentage' => $this->activeOffer->discount_percentage,
        'tag' => $this->activeOffer->tag,
        'starts_at' => $this->activeOffer->starts_at?->toISOString(),
        'ends_at' => $this->activeOffer->ends_at?->toISOString(),
    ];
}, null),
```

**Step 5: Modify ProductDetailResource**

In `app/Http/Resources/ProductDetailResource.php`, make the same changes in `toArray()`.

Replace:
```php
'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
'discount_percentage' => $this->discount_percentage,
```

With:
```php
'discount_price' => $this->effective_price < (float) $this->price
    ? $this->effective_price
    : ($this->discount_price ? (float) $this->discount_price : null),
'discount_percentage' => $this->effective_discount_percentage,
'active_offer' => $this->whenLoaded('activeOffer', function () {
    if (! $this->activeOffer) {
        return null;
    }

    return [
        'id' => $this->activeOffer->id,
        'discount_percentage' => $this->activeOffer->discount_percentage,
        'tag' => $this->activeOffer->tag,
        'starts_at' => $this->activeOffer->starts_at?->toISOString(),
        'ends_at' => $this->activeOffer->ends_at?->toISOString(),
    ];
}, null),
```

**Step 6: Run tests**

Run: `php artisan test --compact tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php`
Expected: All 6 PASS

**Step 7: Run existing product/special-offer tests for regressions**

Run: `php artisan test --compact tests/Feature/Api/V1/PublicSpecialOfferTest.php tests/Feature/Api/V1/VendorSpecialOfferTest.php`
Expected: All existing tests still PASS

**Step 8: Commit**

```bash
git add app/Http/Resources/ProductResource.php app/Http/Resources/ProductDetailResource.php app/Http/Controllers/Api/V1/ProductController.php tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php
git commit -m "feat: expose special offer discounts in product API responses

Product detail and listing endpoints now show the effective discounted
price and percentage when a product has an active special offer.
Also exposes active_offer object with tag and countdown info."
```

---

### Task 3: Make CartService use effective price

**Files:**
- Modify: `app/Services/CartService.php:96-99`
- Create: `tests/Feature/Api/V1/CartSpecialOfferTest.php`

**Step 1: Write failing tests**

Create `tests/Feature/Api/V1/CartSpecialOfferTest.php`:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cart;
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
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Api/V1/CartSpecialOfferTest.php`
Expected: FAIL — cart stores full product price, not special offer price

**Step 3: Modify CartService::addItem**

In `app/Services/CartService.php`, change lines 96-99 from:

```php
$unitPriceCents = isset($data['unit_price_cents'])
    ? $data['unit_price_cents']
    : (int) ($product->price * 100);
```

To:

```php
$unitPriceCents = isset($data['unit_price_cents'])
    ? $data['unit_price_cents']
    : (int) round($product->effective_price * 100);
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Api/V1/CartSpecialOfferTest.php`
Expected: All 4 PASS

**Step 5: Commit**

```bash
git add app/Services/CartService.php tests/Feature/Api/V1/CartSpecialOfferTest.php
git commit -m "fix: cart uses effective price (special offer aware)

CartService::addItem now uses Product::effective_price instead of
raw product price when snapshotting prices. This ensures special
offer discounts are captured in the cart."
```

---

### Task 4: Make OrderController use effective price in fallback path

**Files:**
- Modify: `app/Http/Controllers/Api/V1/OrderController.php:146-148`
- Create: `tests/Feature/Api/V1/OrderSpecialOfferTest.php`

**Step 1: Write failing tests**

Create `tests/Feature/Api/V1/OrderSpecialOfferTest.php`:

```php
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

        $offer = SpecialOffer::factory()->create([
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
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Api/V1/OrderSpecialOfferTest.php`
Expected: FAIL — order uses full price, not special offer price

**Step 3: Modify OrderController fallback price logic**

In `app/Http/Controllers/Api/V1/OrderController.php`, change the fallback price logic (around line 146-148).

Replace:
```php
} else {
    // Fallback for services or items not in cart
    $unitPrice = $orderable->discount_price ?? $orderable->price ?? $orderable->charge_start;
}
```

With:
```php
} else {
    // Fallback for services or items not in cart
    if ($orderable instanceof Product) {
        $unitPrice = $orderable->effective_price;
    } else {
        $unitPrice = $orderable->discount_price ?? $orderable->price ?? $orderable->charge_start;
    }
}
```

Also update the price comparison in the cart-aware path to use effective_price:

Replace:
```php
$currentPrice = $orderable->discount_price ?? $orderable->price;
$currentPriceCents = (int) round($currentPrice * 100);
```

With:
```php
$currentPrice = $orderable->effective_price;
$currentPriceCents = (int) round($currentPrice * 100);
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Api/V1/OrderSpecialOfferTest.php`
Expected: All 4 PASS

**Step 5: Run all order tests for regressions**

Run: `php artisan test --compact tests/Feature/Api/OrderApiTest.php`
Expected: All existing tests still PASS

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/OrderController.php tests/Feature/Api/V1/OrderSpecialOfferTest.php
git commit -m "fix: order creation uses effective price (special offer aware)

OrderController now uses Product::effective_price for both the
cart-aware price comparison and the fallback path. This ensures
special offer discounts are applied at checkout."
```

---

### Task 5: Run full test suite and verify no regressions

**Step 1: Run all tests**

Run: `php artisan test --compact`
Expected: All tests pass, no regressions

---

## Summary of Changes

| File | Change |
|------|--------|
| `app/Models/Product.php` | Add `effective_price` and `effective_discount_percentage` accessors |
| `app/Http/Resources/ProductResource.php` | Use effective price, expose `active_offer` |
| `app/Http/Resources/ProductDetailResource.php` | Same as ProductResource |
| `app/Http/Controllers/Api/V1/ProductController.php` | Eager load `activeOffer` in index/show/showBySlug |
| `app/Services/CartService.php` | Use `effective_price` when snapshotting price |
| `app/Http/Controllers/Api/V1/OrderController.php` | Use `effective_price` in price comparison and fallback |
| `tests/Unit/ProductEffectivePriceTest.php` | 9 tests for accessor logic |
| `tests/Feature/Api/V1/ProductSpecialOfferDisplayTest.php` | 6 tests for API responses |
| `tests/Feature/Api/V1/CartSpecialOfferTest.php` | 4 tests for cart integration |
| `tests/Feature/Api/V1/OrderSpecialOfferTest.php` | 4 tests for order integration |

## What This Fixes

1. **Product detail** now shows special offer discount price and percentage
2. **Cart** snapshots the effective price (including special offer discounts)
3. **Order creation** uses the effective price for checkout
4. **Price mismatch detection** catches when offers start/end while product is in cart
5. **Backward compatible** — products without offers behave exactly as before
