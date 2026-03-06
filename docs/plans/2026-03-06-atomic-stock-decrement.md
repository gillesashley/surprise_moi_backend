# Atomic Stock Decrement Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Prevent race conditions where two concurrent orders oversell a product's stock.

**Architecture:** Replace the check-then-decrement pattern in OrderController::store() with atomic SQL UPDATEs that reserve stock upfront. Track reserved items for rollback on failure.

**Tech Stack:** Laravel Eloquent, PostgreSQL atomic UPDATE, PHPUnit

---

### Task 1: Write failing tests for atomic stock behavior

**Files:**
- Modify: `tests/Feature/Api/OrderApiTest.php`

**Step 1: Write the failing tests**

Add these 4 tests at the end of the `OrderApiTest` class:

```php
public function test_concurrent_orders_for_last_item_only_one_succeeds(): void
{
    $product = Product::factory()->create([
        'vendor_id' => $this->vendor->id,
        'price' => 50.00,
        'discount_price' => null,
        'is_available' => true,
        'stock' => 1,
        'delivery_fee' => 0,
        'free_delivery' => true,
    ]);

    $customer2 = User::factory()->create(['role' => 'customer']);
    $address2 = Address::factory()->create(['user_id' => $customer2->id]);

    $payload1 = [
        'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 1]],
        'delivery_address_id' => $this->address->id,
    ];

    $payload2 = [
        'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 1]],
        'delivery_address_id' => $address2->id,
    ];

    $response1 = $this->actingAs($this->customer)->postJson('/api/v1/orders', $payload1);
    $response2 = $this->actingAs($customer2)->postJson('/api/v1/orders', $payload2);

    $statuses = [$response1->status(), $response2->status()];
    sort($statuses);

    // One should succeed (201), one should fail (422)
    $this->assertEquals([201, 422], $statuses);

    // Stock should be exactly 0, not negative
    $this->assertEquals(0, $product->fresh()->stock);

    // Exactly one order created
    $this->assertDatabaseCount('orders', 1);
}

public function test_multi_item_order_restores_stock_when_second_item_unavailable(): void
{
    $product1 = Product::factory()->create([
        'vendor_id' => $this->vendor->id,
        'price' => 10.00,
        'discount_price' => null,
        'is_available' => true,
        'stock' => 5,
        'delivery_fee' => 0,
        'free_delivery' => true,
    ]);

    $product2 = Product::factory()->create([
        'vendor_id' => $this->vendor->id,
        'price' => 20.00,
        'discount_price' => null,
        'is_available' => true,
        'stock' => 0, // Out of stock
        'delivery_fee' => 0,
        'free_delivery' => true,
    ]);

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/orders', [
            'items' => [
                ['orderable_type' => 'product', 'orderable_id' => $product1->id, 'quantity' => 2],
                ['orderable_type' => 'product', 'orderable_id' => $product2->id, 'quantity' => 1],
            ],
            'delivery_address_id' => $this->address->id,
        ]);

    $response->assertStatus(422);

    // Product 1 stock must be restored to 5 (not 3)
    $this->assertEquals(5, $product1->fresh()->stock);

    // No order created
    $this->assertDatabaseCount('orders', 0);
}

public function test_unlimited_stock_product_skips_stock_check(): void
{
    $product = Product::factory()->create([
        'vendor_id' => $this->vendor->id,
        'price' => 25.00,
        'discount_price' => null,
        'is_available' => true,
        'stock' => null, // Unlimited
        'delivery_fee' => 0,
        'free_delivery' => true,
    ]);

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/orders', [
            'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 100]],
            'delivery_address_id' => $this->address->id,
        ]);

    $response->assertStatus(201);

    // Stock stays null
    $this->assertNull($product->fresh()->stock);
}

public function test_stock_restored_on_order_failure_after_reservation(): void
{
    $product = Product::factory()->create([
        'vendor_id' => $this->vendor->id,
        'price' => 30.00,
        'discount_price' => null,
        'is_available' => true,
        'stock' => 10,
        'delivery_fee' => 0,
        'free_delivery' => true,
    ]);

    // Use an invalid coupon to trigger failure after stock reservation
    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/orders', [
            'items' => [['orderable_type' => 'product', 'orderable_id' => $product->id, 'quantity' => 3]],
            'delivery_address_id' => $this->address->id,
            'coupon_code' => 'INVALID_COUPON_DOES_NOT_EXIST',
        ]);

    $response->assertStatus(422);

    // Stock must be restored to 10
    $this->assertEquals(10, $product->fresh()->stock);
}
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="test_concurrent_orders_for_last_item_only_one_succeeds|test_multi_item_order_restores_stock_when_second_item_unavailable|test_unlimited_stock_product_skips_stock_check|test_stock_restored_on_order_failure_after_reservation" --compact`

Expected: At least `test_concurrent_orders_for_last_item_only_one_succeeds` and `test_multi_item_order_restores_stock_when_second_item_unavailable` and `test_stock_restored_on_order_failure_after_reservation` FAIL. The unlimited stock test may pass already since existing code handles null stock.

**Step 3: Commit failing tests**

```bash
git add tests/Feature/Api/OrderApiTest.php
git commit -m "test: add failing tests for atomic stock reservation"
```

---

### Task 2: Implement atomic stock reservation in OrderController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/OrderController.php:64-253`

**Step 1: Refactor the store method**

Replace the entire `store` method body. The key changes are:

1. **Add `$reservedStock = []` tracker** after `$processedItems = []` (line 89)
2. **Replace the stock check (lines 102-105)** with an atomic decrement that reserves stock immediately
3. **Remove the old stock decrement (lines 223-226)** since stock is now reserved upfront
4. **Add stock restoration in the catch block** before `DB::rollBack()`

Here is the complete updated `store` method — replace `OrderController::store()` (lines 64-254) with:

```php
public function store(StoreOrderRequest $request): JsonResponse
{
    $reservedStock = [];

    try {
        DB::beginTransaction();

        // Check for existing order with same idempotency key
        if ($request->filled('idempotency_key')) {
            $existingOrder = Order::where('user_id', $request->user()->id)
                ->where('idempotency_key', $request->input('idempotency_key'))
                ->first();

            if ($existingOrder) {
                DB::commit();

                return response()->json([
                    'message' => 'Order already exists (idempotent request).',
                    'order' => new OrderResource($existingOrder->load(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
                ], 200);
            }
        }

        $items = $request->input('items');
        $subtotal = 0;
        $vendorId = null;

        $processedItems = [];
        foreach ($items as $item) {
            $orderable = $this->getOrderable($item['orderable_type'], $item['orderable_id']);

            if (! $orderable) {
                throw new \Exception('Item not found: '.$item['orderable_type'].' #'.$item['orderable_id']);
            }

            if ($orderable instanceof Product) {
                if (! $orderable->is_available) {
                    throw new \Exception('Product "'.$orderable->name.'" is not available.');
                }

                // Atomic stock reservation: decrement only if sufficient stock exists
                if ($orderable->stock !== null) {
                    $affected = Product::where('id', $orderable->id)
                        ->where('stock', '>=', $item['quantity'])
                        ->update(['stock' => DB::raw('stock - '.(int) $item['quantity'])]);

                    if ($affected === 0) {
                        throw new \Exception('Insufficient stock for "'.$orderable->name.'".');
                    }

                    $reservedStock[] = ['id' => $orderable->id, 'quantity' => $item['quantity']];
                }
            }

            if ($orderable instanceof Service && $orderable->availability !== 'available') {
                throw new \Exception('Service "'.$orderable->name.'" is not available for booking.');
            }

            $unitPrice = $orderable->discount_price ?? $orderable->price ?? $orderable->charge_start;
            $itemSubtotal = $unitPrice * $item['quantity'];
            $subtotal += $itemSubtotal;

            if ($orderable->vendor_id) {
                if ($vendorId && $vendorId !== $orderable->vendor_id) {
                    throw new \Exception('Cannot order items from multiple vendors in a single order.');
                }
                $vendorId = $orderable->vendor_id;
            }

            $processedItems[] = [
                'orderable' => $orderable,
                'orderable_type' => $item['orderable_type'] === 'product' ? Product::class : Service::class,
                'orderable_id' => $orderable->id,
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'subtotal' => $itemSubtotal,
            ];
        }

        $discountAmount = 0;
        $coupon = null;

        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->input('coupon_code'))->first();

            if (! $coupon || ! $coupon->isValid()) {
                throw new \Exception('Invalid or expired coupon code.');
            }

            if (! $coupon->canBeUsedBy($request->user())) {
                throw new \Exception('You have reached the usage limit for this coupon.');
            }

            $discountAmount = $coupon->calculateDiscount($subtotal);

            if ($discountAmount === 0.0 && $coupon->min_purchase_amount) {
                throw new \Exception('Minimum purchase amount of '.$coupon->currency.' '.$coupon->min_purchase_amount.' required for this coupon.');
            }
        }

        $deliveryFee = 0;
        if (isset($processedItems[0]['orderable']->delivery_fee)) {
            $deliveryFee = $processedItems[0]['orderable']->delivery_fee ?? 0;
        }

        $total = $subtotal - $discountAmount + $deliveryFee;

        // Retry logic for handling duplicate order_number (race condition)
        $maxAttempts = 3;
        $attempt = 0;
        $order = null;

        while ($attempt < $maxAttempts && ! $order) {
            try {
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'vendor_id' => $vendorId,
                    'idempotency_key' => $request->input('idempotency_key'),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'coupon_id' => $coupon?->id,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                    'currency' => config('app.currency', 'USD'),
                    'status' => 'pending',
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                    'delivery_address_id' => $request->input('delivery_address_id'),
                    'special_instructions' => $request->input('special_instructions'),
                    'scheduled_datetime' => $request->input('scheduled_datetime'),
                ]);

                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'orders_order_number_unique')) {
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        throw new \Exception('Failed to generate unique order number after '.$maxAttempts.' attempts. Please try again.');
                    }
                    usleep(50000);

                    continue;
                }

                throw $e;
            }
        }

        if (! $order) {
            throw new \Exception('Failed to create order after multiple attempts.');
        }

        foreach ($processedItems as $processedItem) {
            OrderItem::create([
                'order_id' => $order->id,
                'orderable_type' => $processedItem['orderable_type'],
                'orderable_id' => $processedItem['orderable_id'],
                'variant_id' => $processedItem['variant_id'],
                'quantity' => $processedItem['quantity'],
                'unit_price' => $processedItem['unit_price'],
                'subtotal' => $processedItem['subtotal'],
                'snapshot' => $processedItem['orderable']->toArray(),
            ]);
        }

        if ($coupon) {
            CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $request->user()->id,
                'order_id' => $order->id,
                'discount_amount' => $discountAmount,
                'used_at' => now(),
            ]);

            $coupon->increment('used_count');
        }

        DB::commit();

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order->load(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
        ], 201);
    } catch (\Exception $e) {
        // Restore reserved stock before rolling back
        foreach ($reservedStock as $reserved) {
            Product::where('id', $reserved['id'])->increment('stock', $reserved['quantity']);
        }

        DB::rollBack();

        return response()->json([
            'message' => 'Failed to create order: '.$e->getMessage(),
        ], 422);
    }
}
```

**Step 2: Run all order tests**

Run: `php artisan test tests/Feature/Api/OrderApiTest.php --compact`

Expected: ALL tests pass including the 4 new ones.

**Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/OrderController.php
git commit -m "fix: use atomic stock reservation to prevent overselling race condition"
```

---

### Task 3: Update existing stock test assertion message

**Files:**
- Modify: `tests/Feature/Api/OrderApiTest.php:154-155`

**Step 1: Update the assertion**

The error message changed slightly (removed `. Available: X` suffix). Update line 155:

```php
// Old:
$response->assertJsonFragment(['message' => 'Failed to create order: Insufficient stock for "'.$product->name.'". Available: 5']);

// New:
$response->assertJsonFragment(['message' => 'Failed to create order: Insufficient stock for "'.$product->name.'".']);
```

**Step 2: Run the specific test**

Run: `php artisan test --filter=test_cannot_order_more_than_available_stock --compact`

Expected: PASS

**Step 3: Run full test suite**

Run: `php artisan test tests/Feature/Api/OrderApiTest.php --compact`

Expected: ALL pass

**Step 4: Commit**

```bash
git add tests/Feature/Api/OrderApiTest.php
git commit -m "test: update stock error message assertion to match atomic decrement"
```

---

### Task 4: Final verification and push

**Step 1: Run full test suite**

Run: `php artisan test tests/Feature/Api/OrderApiTest.php --compact`

Expected: All tests pass (should be ~24 tests)

**Step 2: Push branch**

```bash
git push origin fix/atomic-stock-decrement
```
