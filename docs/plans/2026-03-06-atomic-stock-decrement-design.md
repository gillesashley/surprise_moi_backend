# Atomic Stock Decrement for Concurrent Order Safety

## Problem

OrderController::store() has a TOCTOU (time-of-check-time-of-use) race condition.
Stock is checked at line 103, but decremented at line 225. Between those two points,
another request can read the same stock value and both proceed, causing stock to go negative.

## Solution: Atomic Stock Reservation

Replace the check-then-decrement pattern with a single atomic SQL UPDATE that
checks and decrements in one operation:

```sql
UPDATE products SET stock = stock - :qty WHERE id = :id AND stock >= :qty
```

If 0 rows affected, stock was insufficient -- reject immediately.

## Flow

1. For each product item, atomically reserve stock upfront (before creating order)
2. Track reserved items in a `$reservedStock` array
3. If any item fails to reserve, restore previously reserved items and abort
4. Create order and order items as usual (stock already reserved)
5. On any exception in the catch block, restore all reserved stock

## Scope

- Products only (services have no stock/capacity limits)
- Reject immediately on stockout (no partial fulfillment)
- Coupons already use atomic increment -- no change needed

## Rollback

If order creation fails after stock is reserved, the catch block restores stock:

```php
foreach ($reservedStock as $item) {
    Product::where('id', $item['id'])->increment('stock', $item['quantity']);
}
```

## Tests

- Concurrent orders for last-in-stock item (one succeeds, one fails)
- Multi-item order where second item is out of stock (first item restored)
- Order failure after stock reserved (stock restored)
- Null stock (unlimited) products skip stock check
