# Admin Order Management Page — Design Spec

**Date:** 2026-03-16
**Status:** Approved

## Goal

Add an order management section to the admin web dashboard so admins/super_admins can view all orders, filter by status, and update order statuses. Must not break existing functionality.

## Scope

- View-only list with search and status filter
- Dedicated detail page with status update actions
- No editing of order details (receiver info, address, etc.)
- No new models, migrations, or API changes

## New Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/OrderManagementController.php` | Backend: index (paginated list) and show (detail + status update) |
| `resources/js/pages/orders/index.tsx` | Frontend: order list with search, status filter, sortable table |
| `resources/js/pages/orders/show.tsx` | Frontend: order detail with status action buttons |

## Modified Files

| File | Change |
|------|--------|
| `routes/web.php` | Add new routes **above** the SPA catch-all at the bottom of the dashboard group |
| `resources/js/components/app-sidebar.tsx` | Add "Orders" item to admin nav |

## Order List Page — `/dashboard/orders`

### Backend (`OrderManagementController@index`)

- Query `Order::query()` with eager loads: `user`, `vendor`, `items.orderable`
- Soft-deleted orders excluded (default Eloquent behavior — correct for admin view)
- **Search** (optional `?search=`): filter on `order_number` (LIKE) and user name via `whereIn` subquery (not `whereHas`, per project performance convention)
- **Status filter** (optional `?status=`): exact match on `orders.status`
- **Sorting** (optional `?sort_by=`, `?sort_order=`): support `total` and `created_at`, default `created_at desc`
- Paginate 15 per page with `->withQueryString()`
- Return via `Inertia::render('orders/index', [...])`
- Pass `statuses` array for the filter dropdown

### Frontend (`pages/orders/index.tsx`)

- **Layout:** `AppLayout` with breadcrumbs `[Dashboard, Orders]`
- **Card** containing:
  - Header: "Order Management" title, description with total count
  - Search input (300ms debounce) — preserves other query params
  - Status filter dropdown — "All Statuses" default
  - Table columns: Order #, Customer, Vendor, Items (count), Total (GHS), Status (badge), Payment (badge), Date
  - Rows clickable — navigate to `/dashboard/orders/{id}`
  - Pagination: Previous / Page X of Y / Next
- **Status badges:** colored per status (pending=yellow, confirmed=blue, processing=purple, fulfilled=green, shipped=indigo, delivered=green, refunded=red)
- **Payment badges:** unpaid=red, pending=yellow, paid=green, failed=red, refunded=orange

### Props Interface

```typescript
interface Props {
  orders: {
    data: Order[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  statuses: string[];
  filters: {
    search?: string;
    status?: string;
    sort_by?: string;
    sort_order?: string;
  };
}
```

## Order Detail Page — `/dashboard/orders/{order}`

### Backend (`OrderManagementController@show`)

- Find order with eager loads: `user`, `vendor`, `items.orderable`, `deliveryAddress`, `coupon`, `latestPayment`
- Return via `Inertia::render('orders/show', ['order' => ...])`
- Pass `allowedTransitions` array based on current status

### Backend (`OrderManagementController@updateStatus`)

- Accept `POST /dashboard/orders/{order}/status` with `status` field
- Validate against allowed transitions using inline validation in the controller (new FormRequest not needed — simple `in:` rule against the allowed transitions array)
- Authorization: checked by `dashboard` middleware (admin/super_admin). Does NOT reuse `UpdateOrderStatusRequest` (which is for the API and has vendor-specific logic).
- Status update logic:
  - `confirmed` → `$order->markAsConfirmed()`
  - `processing` → `$order->update(['status' => 'processing'])` (no dedicated model method exists)
  - `fulfilled` → `$order->markAsFulfilled()`
  - `shipped` → `$order->markAsShipped()`
  - `delivered` → `$order->markAsDelivered()`
- For fulfillment/shipping/delivery: call `VendorBalanceService::releaseFunds()` if order is paid AND `$order->funds_released` is false (prevent double release)
- Redirect back with success flash message

### Status Transitions

| Current | Allowed Next |
|---------|-------------|
| pending | confirmed |
| confirmed | processing, fulfilled |
| processing | fulfilled |
| fulfilled | shipped |
| shipped | delivered |

Refund is out of scope for this iteration.

### Frontend (`pages/orders/show.tsx`)

- **Layout:** `AppLayout` with breadcrumbs `[Dashboard, Orders, Order #XXX]`
- **Header section:** Back link, order number (large), status badge, payment badge
- **Status actions:** Buttons for each allowed transition. Confirmation dialog before executing. Uses Inertia `router.post()`.
- **Sections in a 2-column grid (responsive to single column on mobile):**

**Left column:**
1. **Order Items** — table: thumbnail, name, qty, unit price, subtotal
2. **Financial Summary** — subtotal, discount, delivery fee, total, commission info

**Right column:**
3. **Customer** — name, email, phone
4. **Vendor** — name, email (loaded explicitly from the vendor relationship, not from OrderResource)
5. **Delivery** — receiver name, receiver phone, address fields (`address_line_1`, city, state, postal_code, country)
6. **Payment** — status, reference, channel, amount, paid_at
7. **Timeline** — created_at, confirmed_at, fulfilled_at, shipped_at, delivered_at (show only non-null)

### Props Interface

```typescript
interface Props {
  order: {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    subtotal: number;
    discount_amount: number;
    delivery_fee: number;
    total: number;
    platform_commission_rate: number;
    platform_commission_amount: number;
    vendor_payout_amount: number;
    currency: string;
    receiver_name: string | null;
    receiver_phone: string | null;
    special_instructions: string | null;
    occasion: string | null;
    tracking_number: string | null;
    created_at: string;
    confirmed_at: string | null;
    fulfilled_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    vendor: { id: number; name: string; email: string } | null;
    delivery_address: { address_line_1: string | null; city: string; state: string; postal_code: string; country: string } | null;
    items: Array<{
      id: number;
      quantity: number;
      unit_price: number;
      subtotal: number;
      orderable: { id: number; name: string; thumbnail: string | null };
    }>;
    latest_payment: {
      reference: string;
      status: string;
      channel: string | null;
      amount: number;
      paid_at: string | null;
    } | null;
  };
  allowedTransitions: string[];
}
```

## Routing — Critical Note

All new routes MUST be registered **above** the SPA catch-all route at the bottom of the dashboard group in `routes/web.php`:

```php
// Order management (add ABOVE the catch-all)
Route::get('orders', [OrderManagementController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderManagementController::class, 'show'])->name('orders.show');
Route::post('orders/{order}/status', [OrderManagementController::class, 'updateStatus'])->name('orders.update-status');

// This catch-all MUST remain last
Route::get('/{any?}', [AdminDashboardController::class, 'index'])->where('any', '.*')->name('dashboard.spa');
```

## Sidebar Navigation

Add to `getNavItemsForRole()` in `app-sidebar.tsx`, as a top-level item:

```
Orders (icon: ShoppingCart from lucide-react)
  → /dashboard/orders
```

Single item, no sub-menu needed.

## Authorization

- Uses existing `dashboard` middleware — restricts to admin/super_admin roles
- Does NOT reuse `UpdateOrderStatusRequest` (that is API-specific with vendor logic)
- Status update validation is inline in the controller method
- No new policies needed

## Patterns to Follow

- MUI `Box`/`Typography` for layout (matches existing pages)
- shadcn/ui `Card`, `Button`, `Input`, `Badge`, `Dialog` for UI components
- Inertia `router.visit()` for navigation with query params
- Inertia `router.post()` for status updates
- 300ms debounce on search input
- Confirmation dialog before status changes
- Sonner toast for success/error feedback after status update
- Use `whereIn` subquery (not `whereHas`) for search by user name

## What This Does NOT Change

- No changes to API controllers (`OrderController`)
- No changes to Order model or migrations
- No changes to existing frontend pages
- No changes to existing route definitions (only new routes added)
- No changes to payment flow or notification system

## Testing

Feature tests for `OrderManagementController`:
- Index returns paginated orders for admin users
- Index search filters by order number and customer name
- Index status filter works
- Non-admin users cannot access the routes (403)
- Show returns order with all relationships loaded
- Status update: valid transitions succeed
- Status update: invalid transitions are rejected (e.g., pending → shipped)
- Status update: fund release called only when order is paid and not already released
- No frontend tests (consistent with existing dashboard pages)
