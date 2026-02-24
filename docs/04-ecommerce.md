# E-commerce System

This document covers the product catalog, shopping cart, order management, and review systems.

## Overview

Surprise Moi is a multi-vendor marketplace where vendors can sell:

- **Products** - Physical goods with variants, images, and inventory tracking
- **Services** - Bookable services with flexible pricing ranges

## Core Models

### Product

**Location**: `app/Models/Product.php`

Physical items that can be purchased and delivered.

#### Attributes

```php
[
    'category_id',              // Product category
    'vendor_id',                // Vendor who owns this product
    'shop_id',                  // Shop where product is listed
    'name',                     // Product name
    'description',              // Short description
    'detailed_description',     // Full product details
    'price',                    // Base price (decimal)
    'discount_price',           // Sale price if applicable (decimal)
    'discount_percentage',      // Discount % for display
    'currency',                 // USD, GHS, etc.
    'thumbnail',                // Main product image
    'stock',                    // Available quantity (null = unlimited)
    'is_available',             // Active listing (boolean)
    'is_featured',              // Featured on homepage (boolean)
    'rating',                   // Average rating (decimal)
    'reviews_count',            // Total reviews
    'sizes',                    // Available sizes (array: ['S', 'M', 'L'])
    'colors',                   // Available colors (array: ['Red', 'Blue'])
    'free_delivery',            // Free shipping flag (boolean)
    'delivery_fee',             // Shipping cost (decimal)
    'estimated_delivery_days',  // Delivery time estimate
    'return_policy',            // Return terms
]
```

#### Relationships

```php
$product->category()        // BelongsTo Category
$product->vendor()          // BelongsTo User (vendor)
$product->shop()            // BelongsTo Shop
$product->images()          // HasMany ProductImage (ordered by sort_order)
$product->variants()        // HasMany ProductVariant (size/color combinations)
$product->tags()            // BelongsToMany Tag
$product->reviews()         // MorphMany Review
```

#### Key Features

**Soft Deletes**: Products are soft-deleted to preserve order history.

**Stock Management**: Stock is decremented when orders are confirmed:

```php
if ($product->stock !== null) {
    $product->decrement('stock', $quantity);
}
```

### Service

**Location**: `app/Models/Service.php`

Bookable services with flexible pricing.

#### Attributes

```php
[
    'vendor_id',          // Service provider
    'shop_id',            // Shop listing
    'name',               // Service name
    'description',        // Service details
    'service_type',       // Category (e.g., 'delivery', 'consultation')
    'charge_start',       // Minimum price (decimal)
    'charge_end',         // Maximum price (decimal)
    'currency',
    'thumbnail',
    'availability',       // 'available', 'unavailable', 'by_appointment'
    'rating',
    'reviews_count',
]
```

#### Relationships

```php
$service->vendor()     // BelongsTo User
$service->shop()       // BelongsTo Shop
$service->reviews()    // MorphMany Review
```

### Shop

**Location**: `app/Models/Shop.php`

A vendor's storefront for organizing products and services.

#### Attributes

```php
[
    'vendor_id',      // Owner
    'category_id',    // Shop category
    'name',           // Shop name
    'owner_name',     // Owner's display name
    'slug',           // URL-friendly identifier (auto-generated)
    'description',
    'logo',           // Shop logo image
    'is_active',      // Published status (boolean)
    'location',       // Physical location
    'phone',
    'email',
]
```

#### Slug Generation

Slugs are auto-generated from the shop name:

```php
protected static function boot(): void
{
    parent::boot();

    static::creating(function (Shop $shop) {
        if (empty($shop->slug)) {
            $shop->slug = Str::slug($shop->name);
        }
    });
}
```

#### Scopes

```php
Shop::active()->get()  // Only active shops
```

### Category

**Location**: `app/Models/Category.php`

Hierarchical product/service categories.

```php
[
    'name',
    'slug',
    'description',
    'parent_id',    // For nested categories
    'image',        // Category image
    'type',         // 'product' or 'service'
    'is_active',
]
```

Categories can have subcategories:

```php
$category->children()  // HasMany Category
$category->parent()    // BelongsTo Category
```

### Cart System

**Location**: `app/Models/Cart.php`, `app/Models/CartItem.php`

#### Cart Model

Carts support both authenticated users and guest sessions.

```php
[
    'user_id',           // null for guests
    'cart_token',        // UUID for guest sessions
    'currency',
    'subtotal_cents',    // Prices stored as cents (integer)
    'shipping_cents',
    'tax_cents',
    'discount_cents',
    'total_cents',
    'metadata',          // JSON for additional data
    'version',           // Optimistic locking
]
```

**Guest Cart Creation**:

```php
static::creating(function ($cart) {
    if (empty($cart->cart_token) && empty($cart->user_id)) {
        $cart->cart_token = Str::uuid();
    }
});
```

**Price Accessors** (convert cents to decimal):

```php
public function getSubtotalAttribute(): float {
    return $this->subtotal_cents / 100;
}
```

**Recalculation**:

```php
public function recalculateTotals(): void
{
    $this->subtotal_cents = $this->items->sum('line_total_cents');
    $this->total_cents = $this->subtotal_cents + $this->shipping_cents
                        + $this->tax_cents - $this->discount_cents;
}
```

#### CartItem Model

Individual items in a cart.

```php
[
    'cart_id',
    'product_id',          // Reference to product
    'variant_id',          // ProductVariant if applicable
    'quantity',
    'unit_price_cents',    // Price at time of adding
    'line_total_cents',    // quantity * unit_price_cents
    'metadata',            // Customization options
]
```

### Order System

**Location**: `app/Models/Order.php`, `app/Models/OrderItem.php`

#### Order Model

Orders are created when a cart is checked out.

```php
[
    'order_number',          // Auto-generated (ORD-XXXXXXXXXX)
    'user_id',
    'vendor_id',             // Primary vendor (orders from one vendor only)
    'subtotal',
    'discount_amount',
    'coupon_id',             // Applied coupon
    'delivery_fee',
    'total',
    'currency',
    'status',                // Order status
    'payment_status',        // Payment status
    'delivery_address_id',   // Shipping address
    'special_instructions',  // Customer notes
    'occasion',              // Gift occasion (birthday, anniversary, etc.)
    'scheduled_datetime',    // Delivery scheduling
    'tracking_number',       // Shipping tracking
    'cancellation_reason',
    'confirmed_at',          // Vendor confirmed
    'fulfilled_at',          // Order fulfilled/shipped
    'delivered_at',          // Successfully delivered
    'cancelled_at',
]
```

#### Order Statuses

```php
// Order status
'pending'     // Awaiting vendor confirmation
'confirmed'   // Vendor accepted
'processing'  // Being prepared
'fulfilled'   // Shipped/ready for pickup
'delivered'   // Successfully delivered
'cancelled'   // Cancelled by user or vendor
'failed'      // Payment/processing failed

// Payment status
'unpaid'      // No payment attempt
'pending'     // Payment initiated
'paid'        // Successfully paid
'failed'      // Payment failed
'refunded'    // Money returned
```

#### Order Generation

Order numbers are auto-generated on creation:

```php
protected static function boot(): void
{
    parent::boot();

    static::creating(function ($order) {
        if (empty($order->order_number)) {
            $order->order_number = 'ORD-' . strtoupper(Str::random(10));
        }
    });
}
```

#### OrderItem Model

Polymorphic items (can be Product or Service).

```php
[
    'order_id',
    'orderable_type',     // 'App\Models\Product' or 'App\Models\Service'
    'orderable_id',       // ID of product/service
    'variant_id',         // ProductVariant for products
    'quantity',
    'unit_price',         // Price at time of purchase
    'subtotal',           // quantity * unit_price
    'customization',      // Custom options (JSON)
]
```

**Polymorphic Relationship**:

```php
$orderItem->orderable()  // MorphTo Product or Service
```

### Review System

**Location**: `app/Models/Review.php`

Polymorphic reviews for products, services, and vendors.

```php
[
    'user_id',           // Reviewer
    'reviewable_type',   // Product, Service, or User (vendor)
    'reviewable_id',
    'rating',            // 1-5 stars
    'title',
    'body',              // Review text
    'verified_purchase', // Only customers who ordered can review (boolean)
    'helpful_count',     // Upvotes
]
```

**Relationships**:

```php
$review->user()         // BelongsTo User (reviewer)
$review->reviewable()   // MorphTo (Product, Service, or User)
```

## API Endpoints

### Products

**Controller**: `app/Http/Controllers/Api/V1/ProductController.php`

#### List Products

`GET /api/v1/products`

**Query Parameters**:

- `category_id` - Filter by category
- `vendor_id` - Filter by vendor
- `shop_id` - Filter by shop
- `is_featured` - Featured products only
- `search` - Search by name/description
- `min_price`, `max_price` - Price range
- `colors[]` - Filter by colors
- `sizes[]` - Filter by sizes
- `sort_by` - 'price_asc', 'price_desc', 'newest', 'popular'
- `per_page` - Pagination (default: 15)

**Response**:

```json
{
    "data": [
        {
            "id": 1,
            "name": "Gift Basket",
            "price": 50.00,
            "discount_price": 40.00,
            "thumbnail": "/storage/products/abc.jpg",
            "rating": 4.5,
            "reviews_count": 23,
            "vendor": { ... },
            "category": { ... }
        }
    ],
    "meta": { "total": 100, "current_page": 1 }
}
```

#### View Product

`GET /api/v1/products/{product}`

Returns detailed product with images, variants, and related data:

```php
$product->load([
    'category',
    'vendor',
    'shop',
    'images',
    'variants',
    'tags',
]);
```

#### Create Product (Vendor Only)

`POST /api/v1/products`

**Required Fields**:

- `shop_id` - Must belong to authenticated vendor
- `category_id`
- `name`
- `price`

**Optional**:

- `description`, `detailed_description`
- `discount_price`
- `thumbnail` (file upload)
- `stock`
- `sizes[]`, `colors[]`
- `delivery_fee`, `free_delivery`

**Authorization**: Vendor must own the shop.

#### Update Product

`PUT /api/v1/products/{product}`

Same fields as create. Vendor must own the product.

#### Delete Product

`DELETE /api/v1/products/{product}`

Soft deletes the product (preserved for order history).

### Services

**Controller**: `app/Http/Controllers/Api/V1/ServiceController.php`

Endpoints mirror products:

- `GET /api/v1/services` - List services
- `GET /api/v1/services/{service}` - View service
- `POST /api/v1/services` - Create service (vendor)
- `PUT /api/v1/services/{service}` - Update service
- `DELETE /api/v1/services/{service}` - Delete service

### Shops

**Controller**: `app/Http/Controllers/Api/V1/ShopController.php`

#### List Shops

`GET /api/v1/shops`

**Query Parameters**:

- `category_id` - Filter by category
- `is_active` - Active only (default: true)
- `search` - Search by name

#### View Shop

`GET /api/v1/shops/{shop}`

Returns shop with vendor details.

#### Shop Products

`GET /api/v1/shops/{shop}/products`

List all products in a shop (with filtering).

#### Shop Services

`GET /api/v1/shops/{shop}/services`

List all services in a shop.

#### My Shops (Vendor)

`GET /api/v1/my-shops`

Returns all shops owned by authenticated vendor.

#### Create Shop (Vendor)

`POST /api/v1/shops`

**Required**:

- `name`
- `category_id`

**Optional**:

- `description`, `logo`, `location`, `phone`, `email`

#### Update Shop

`PUT /api/v1/shops/{shop}`

Vendor must own the shop.

### Cart

**Controller**: `app/Http/Controllers/Api/V1/CartController.php`  
**Service**: `app/Services/CartService.php`

#### View Cart

`GET /api/v1/cart`

**Headers**:

- `X-Cart-Token` - For guest users
- `Authorization: Bearer {token}` - For authenticated users

Returns cart with all items, including product/service details.

#### Add to Cart

`POST /api/v1/cart/items`

**Request**:

```json
{
    "product_id": 5,
    "variant_id": 12, // Optional
    "quantity": 2
}
```

or for services:

```json
{
    "service_id": 8,
    "quantity": 1
}
```

**Logic**:

- If item exists, quantity is increased
- If new item, CartItem is created
- Cart totals are recalculated

#### Update Cart Item

`PATCH /api/v1/cart/items/{cartItem}`

**Request**:

```json
{
    "quantity": 3
}
```

#### Remove from Cart

`DELETE /api/v1/cart/items/{cartItem}`

#### Clear Cart

`POST /api/v1/cart/clear`

Removes all items from cart.

#### Merge Carts

`POST /api/v1/cart/merge`

**Authentication Required**

Merges guest cart (via `X-Cart-Token`) with user's cart upon login:

```php
// Find guest cart
$guestCart = Cart::where('cart_token', $request->header('X-Cart-Token'))->first();

// Merge items into user cart
foreach ($guestCart->items as $item) {
    // Add to user cart or update quantity
}

// Delete guest cart
$guestCart->delete();
```

### Orders

**Controller**: `app/Http/Controllers/Api/V1/OrderController.php`

#### List Orders

`GET /api/v1/orders`

**Query Parameters**:

- `status` - Filter by order status
- `search` - Search order/tracking number
- `per_page` - Pagination

**Role-based filtering**:

- Customers see their own orders
- Vendors see orders for their products/services
- Admins see all orders

#### Create Order

`POST /api/v1/orders`

**Request**:

```json
{
    "items": [
        {
            "orderable_type": "product",
            "orderable_id": 5,
            "variant_id": 12,
            "quantity": 2
        },
        {
            "orderable_type": "service",
            "orderable_id": 8,
            "quantity": 1
        }
    ],
    "delivery_address_id": 3,
    "coupon_code": "SAVE10",
    "special_instructions": "Ring doorbell",
    "scheduled_datetime": "2026-02-15T14:00:00Z",
    "occasion": "birthday"
}
```

**Process**:

1. Validate all items exist and are available
2. Check stock for products
3. Validate coupon if provided
4. Calculate totals (subtotal, discount, delivery, total)
5. Create Order and OrderItems
6. Decrement stock for products
7. Record coupon usage
8. Return order with payment initiation details

**Important Constraints**:

- All items must be from the same vendor
- Products must have sufficient stock
- Services must be available

#### View Order

`GET /api/v1/orders/{order}`

Returns order with all items, address, and payment details.

**Authorization**: User must be order owner, the vendor, or admin.

#### Update Order Status

`POST /api/v1/orders/{order}/status`

**Request**:

```json
{
    "status": "confirmed"
}
```

**Vendor Actions**:

- `confirmed` - Accept order
- `processing` - Begin fulfillment
- `fulfilled` - Mark as shipped/ready
- `delivered` - Mark as delivered

**Side Effects**:
When order is marked `delivered` and paid:

- Vendor balance is credited
- Platform commission is calculated
- VendorTransaction is recorded

#### Cancel Order

`POST /api/v1/orders/{order}/cancel`

**Request**:

```json
{
    "reason": "Changed my mind"
}
```

**Rules**:

- Can only cancel `pending` orders
- Stock is returned for products
- Refund initiated if already paid

#### Track Order

`GET /api/v1/orders/{order}/track`

Returns order status history and tracking information.

#### Order Statistics

`GET /api/v1/orders/statistics`

Returns summary stats for user:

```json
{
    "total_orders": 45,
    "pending": 3,
    "delivered": 40,
    "cancelled": 2,
    "total_spent": 2345.67
}
```

### Reviews

**Controller**: `app/Http/Controllers/Api/V1/ReviewController.php`

#### List User's Reviews

`GET /api/v1/reviews`

Returns all reviews by authenticated user.

#### Product Reviews

`GET /api/v1/products/{product}/reviews`

Public endpoint for viewing product reviews.

#### Service Reviews

`GET /api/v1/services/{service}/reviews`

Public endpoint for viewing service reviews.

#### Vendor Reviews

`GET /api/v1/vendors/{vendor}/reviews`

Reviews about a vendor (across all products/services).

#### Create Review

`POST /api/v1/reviews`

**Request**:

```json
{
    "reviewable_type": "product", // or "service" or "vendor"
    "reviewable_id": 5,
    "rating": 5,
    "title": "Amazing product!",
    "body": "Exceeded my expectations..."
}
```

**Validation**:

- Must have purchased the item
- Can only review once per item
- Rating must be 1-5

**Side Effects**:

- Updates average rating on reviewable model
- Increments `reviews_count`

#### Update Review

`PUT /api/v1/reviews/{review}`

User can edit their own review.

#### Delete Review

`DELETE /api/v1/reviews/{review}`

User can delete their own review. Recalculates ratings.

### Coupons

**Controller**: `app/Http/Controllers/Api/V1/CouponController.php`  
**Model**: `app/Models/Coupon.php`

#### Coupon Model

```php
[
    'code',                 // Unique coupon code (e.g., 'SAVE10')
    'discount_type',        // 'percentage' or 'fixed'
    'discount_value',       // 10 (for 10%) or 50.00 (for $50 off)
    'currency',
    'min_purchase_amount',  // Minimum order subtotal
    'max_discount_amount',  // Cap for percentage discounts
    'usage_limit',          // Total uses allowed (null = unlimited)
    'usage_per_user',       // Per-user limit (null = unlimited)
    'valid_from',
    'valid_until',
    'is_active',
]
```

#### Available Coupons

`GET /api/v1/coupons/available`

Returns coupons user can currently use.

#### Apply Coupon

`POST /api/v1/coupons/apply`

**Request**:

```json
{
    "code": "SAVE10",
    "order_total": 100.0
}
```

**Response**:

```json
{
    "valid": true,
    "discount_amount": 10.0,
    "final_total": 90.0
}
```

**Validation**:

- Code exists and is active
- Current date is within valid_from/valid_until
- User hasn't exceeded usage_per_user limit
- Order meets min_purchase_amount

#### Coupon Calculation

```php
public function calculateDiscount(float $orderTotal): float
{
    if ($this->discount_type === 'percentage') {
        $discount = ($orderTotal * $this->discount_value) / 100;

        // Apply max discount cap
        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    // Fixed amount discount
    return min($this->discount_value, $orderTotal);
}
```

## Business Logic Services

### VendorBalanceService

**Location**: `app/Services/VendorBalanceService.php`

Handles vendor financial operations.

#### Credit Balance (After Delivery)

```php
public function creditForOrder(Order $order): void
{
    $vendorAmount = $order->total * 0.85; // Vendor gets 85%
    $platformFee = $order->total * 0.15;  // Platform takes 15%

    // Update vendor balance
    $balance = VendorBalance::firstOrCreate(['vendor_id' => $order->vendor_id]);
    $balance->increment('available_balance', $vendorAmount);

    // Record transaction
    VendorTransaction::create([
        'vendor_id' => $order->vendor_id,
        'order_id' => $order->id,
        'type' => 'credit',
        'amount' => $vendorAmount,
        'description' => "Payment for order #{$order->order_number}",
    ]);
}
```

### CartService

**Location**: `app/Services/CartService.php`

Centralizes cart operations for consistency.

#### Get or Create Cart

```php
public function getOrCreateCart(User $user = null, string $cartToken = null): Cart
{
    if ($user) {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    return Cart::firstOrCreate(['cart_token' => $cartToken]);
}
```

#### Add Item

```php
public function addItem(Cart $cart, array $data): CartItem
{
    // Check if item exists
    $existing = $cart->items()
        ->where('product_id', $data['product_id'])
        ->where('variant_id', $data['variant_id'] ?? null)
        ->first();

    if ($existing) {
        $existing->increment('quantity', $data['quantity']);
        return $existing->fresh();
    }

    // Create new item
    $product = Product::findOrFail($data['product_id']);
    $unitPrice = $product->discount_price ?? $product->price;

    $item = $cart->items()->create([
        'product_id' => $data['product_id'],
        'variant_id' => $data['variant_id'] ?? null,
        'quantity' => $data['quantity'],
        'unit_price_cents' => $unitPrice * 100,
        'line_total_cents' => $unitPrice * 100 * $data['quantity'],
    ]);

    $cart->recalculateTotals();
    $cart->save();

    return $item;
}
```

## Testing

### Feature Tests

```php
// tests/Feature/ProductTest.php

public function test_can_list_products(): void
{
    Product::factory()->count(10)->create();

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
            ->assertJsonCount(10, 'data');
}

public function test_vendor_can_create_product(): void
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    $shop = Shop::factory()->create(['vendor_id' => $vendor->id]);

    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/v1/products', [
        'shop_id' => $shop->id,
        'category_id' => Category::factory()->create()->id,
        'name' => 'Test Product',
        'price' => 50.00,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('products', ['name' => 'Test Product']);
}
```

### Order Flow Test

```php
public function test_complete_order_flow(): void
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $address = Address::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    // Create order
    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 2]
        ],
        'delivery_address_id' => $address->id,
    ]);

    $response->assertStatus(201);

    $order = Order::first();
    $this->assertEquals('pending', $order->status);
    $this->assertEquals(8, $product->fresh()->stock); // Stock decremented
}
```

---

This e-commerce system provides a complete multi-vendor marketplace with flexible product/service listings, intelligent cart management, and comprehensive order tracking.
