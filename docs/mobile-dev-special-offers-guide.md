# Special Offers API Integration Guide for Mobile (Flutter)

## What Changed

Special offer discounts now flow through the entire backend pipeline: product detail, cart, and checkout. Previously, special offers were display-only — now the discounted price is applied everywhere automatically.

---

## API Endpoints

### 1. Browse Special Offers (unchanged)

```
GET /api/v1/special-offers?page=1&per_page=15
```

No auth required. Returns paginated list of active special offers.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "discount_percentage": 30,
      "tag": "Flash Sale",
      "starts_at": "2026-03-06T00:00:00.000000Z",
      "ends_at": "2026-03-13T23:59:59.000000Z",
      "is_active": true,
      "product": {
        "id": 34,
        "name": "Watch & Glasses",
        "price": 99.0,
        "discounted_price": 69.3,
        "thumbnail": "https://...",
        "images": ["https://...", "https://..."],
        "shop": {
          "id": 5,
          "name": "Music Flow"
        }
      },
      "created_at": "2026-03-06T08:59:20.000000Z"
    }
  ],
  "links": { ... },
  "meta": { "current_page": 1, "total": 1, ... }
}
```

### 2. Product Detail (NEW FIELDS)

```
GET /api/v1/products/{id}
```

**What's new:** When a product has an active special offer, the response now includes:
- `discount_price` — the effective discounted price (was `null` before if no vendor discount)
- `discount_percentage` — the effective discount percentage
- `active_offer` — a new object with offer details (or `null` if no active offer)

**Response with active special offer:**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 34,
      "name": "Watch & Glasses",
      "price": 99.0,
      "discount_price": 69.3,
      "discount_percentage": 30,
      "active_offer": {
        "id": 1,
        "discount_percentage": 30,
        "tag": "Flash Sale",
        "starts_at": "2026-03-06T00:00:00.000000Z",
        "ends_at": "2026-03-13T23:59:59.000000Z"
      },
      "currency": "GHS",
      "stock": 7,
      "is_available": true,
      ...
    }
  }
}
```

**Response without special offer (unchanged behavior):**
```json
{
  "data": {
    "product": {
      "id": 30,
      "price": 5.0,
      "discount_price": null,
      "discount_percentage": null,
      "active_offer": null,
      ...
    }
  }
}
```

### 3. Product Listing (NEW FIELDS)

```
GET /api/v1/products?per_page=20
```

Same new fields as product detail: `discount_price`, `discount_percentage`, and `active_offer` are now included in listing responses too. This means product cards in grids/lists can show the offer tag and discounted price without extra API calls.

### 4. Add to Cart (no client changes needed)

```
POST /api/v1/cart/items
```

**Body:** `{ "product_id": 34, "quantity": 1 }`

The backend now automatically uses the special offer price when adding to cart. You do NOT need to send `unit_price_cents` — the backend calculates it. The response will show the correct discounted price in `unit_price_cents`.

**Response:**
```json
{
  "success": true,
  "data": {
    "cart": {
      "total_cents": 6930,
      "total": 69.3,
      ...
    },
    "item": {
      "unit_price_cents": 6930,
      "unit_price": 69.3,
      "quantity": 1,
      "line_total_cents": 6930,
      "line_total": 69.3,
      ...
    }
  }
}
```

### 5. Create Order (no client changes needed)

```
POST /api/v1/orders
```

The order will automatically use the correct effective price (from cart or computed).

**Important:** If a special offer starts or ends while a product is in the user's cart, the checkout will return a **409 Conflict** with a `price_changed` error. The app should handle this by refreshing the cart.

**409 Response (price changed):**
```json
{
  "code": "price_changed",
  "message": "Price for \"Watch & Glasses\" has changed from GH₵99.00 to GH₵69.30. Please refresh your cart.",
  "product": "Watch & Glasses",
  "cart_price": 99.0,
  "current_price": 69.3
}
```

---

## What the Flutter App Should Do

### Product Detail Screen

When `active_offer` is not null:
1. Show a **sale badge/banner** with the `active_offer.tag` (e.g., "Flash Sale", "Limited Time!", "Today's Offers")
2. Show the original `price` with ~~strikethrough~~ and the `discount_price` highlighted
3. Show the `discount_percentage` (e.g., "30% OFF")
4. Optionally show a **countdown timer** using `active_offer.ends_at`

When `active_offer` is null:
- If `discount_price` is not null → show vendor discount (existing behavior)
- If `discount_price` is null → show regular price (existing behavior)

### Product Listing / Grid Cards

Same logic as above. The `active_offer` field is available on listing responses too, so you can show offer badges on product cards throughout the app (not just the special offers screen).

### Cart Screen

No changes needed. The cart already shows `unit_price` and `line_total` — these now reflect the special offer price automatically.

### Checkout / Order Creation

Handle the `409` status code with `code: "price_changed"`:
1. Show a dialog: "Price for {product} has changed. Please refresh your cart."
2. Re-fetch the cart (`GET /api/v1/cart`) to get updated prices
3. Let the user review and retry checkout

### Special Offers Screen

No changes needed. The existing `GET /api/v1/special-offers` endpoint works the same way.

---

## Vendor Endpoints (Product Management Screen)

### Create Special Offer
```
POST /api/v1/vendor/special-offers
Authorization: Bearer {vendor_token}
```

**Body:**
```json
{
  "product_id": 34,
  "discount_percentage": 30,
  "tag": "Flash Sale",
  "starts_at": "2026-03-06 00:00:00",
  "ends_at": "2026-03-13 23:59:59"
}
```

**Valid tags:** `"Today's Offers"`, `"Limited Time!"`, `"Special Offers"`, `"Festival Offers"`, `"Flash Sale"`

**Validation rules:**
- `product_id` — must belong to the vendor's shop, must not already have an active offer
- `discount_percentage` — integer, 1-99
- `tag` — must be one of the valid tags above
- `starts_at` — valid datetime
- `ends_at` — must be after `starts_at` and after now

### List Vendor's Offers
```
GET /api/v1/vendor/special-offers
Authorization: Bearer {vendor_token}
```

### Update Offer
```
PUT /api/v1/vendor/special-offers/{id}
Authorization: Bearer {vendor_token}
```

All fields are optional (partial update).

### Delete Offer
```
DELETE /api/v1/vendor/special-offers/{id}
Authorization: Bearer {vendor_token}
```

---

## Quick Reference: New/Changed Fields

| Endpoint | New Field | Type | Description |
|----------|-----------|------|-------------|
| `GET /products/{id}` | `active_offer` | object or null | Active special offer details |
| `GET /products/{id}` | `discount_price` | float or null | Now reflects special offer price when active |
| `GET /products/{id}` | `discount_percentage` | int or null | Now reflects special offer % when active |
| `GET /products` | Same 3 fields | Same | Available on listing too |
| `GET /products/by-slug/{slug}` | Same 3 fields | Same | Available on slug lookup too |

### `active_offer` Object Shape
```json
{
  "id": 1,
  "discount_percentage": 30,
  "tag": "Flash Sale",
  "starts_at": "2026-03-06T00:00:00.000000Z",
  "ends_at": "2026-03-13T23:59:59.000000Z"
}
```

---

## Testing Checklist

1. Open the app → Home screen → Special Offers carousel should show the offer
2. Tap any offer → Product detail should show discounted price AND the offer tag/badge
3. Add to cart → Cart total should use the discounted price
4. Checkout → Order total should match the cart total
5. Vendor creates offer in Product Management → it appears in customer's special offers
6. Vendor deletes offer → product detail goes back to normal price
7. Edge case: if user has product in cart at full price and a new offer drops the price, checkout returns 409 → handle gracefully
