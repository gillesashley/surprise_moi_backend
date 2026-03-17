# Paystack Payments Dashboard — Design Spec

## Problem

Admins have no visibility into Paystack payment state from the dashboard. When a webhook/callback fails silently (as happened with vendor `noelcassie44@gmail.com`), payments get stuck in `pending` locally even though Paystack processed them successfully. Admins must log into the Paystack dashboard to investigate — slow and disconnected from app context.

## Solution

A new admin dashboard page that lists all payments (order payments + vendor onboarding payments) from the local database, with on-demand Paystack verification and a manual sync action to fix stuck payments.

## Scope

- Browse all payments (searchable, filterable by status/type/date)
- Verify any payment against the Paystack API
- View detailed Paystack response data (channel, gateway response, transaction log, card/momo details)
- Sync local records when Paystack and local state diverge (with admin confirmation)

Out of scope for now: refunds, CSV/PDF export, background auto-sync job.

## Architecture: Local DB + On-Demand Paystack Verify

List page queries local `payments` and `vendor_onboarding_payments` tables. Paystack API is only called when an admin explicitly clicks "Verify on Paystack" on the detail page.

**Why this approach:**
- Fast local queries with full filtering on app-specific data (user name, email, order number)
- No Paystack rate-limit concerns on page loads
- Works even if Paystack is temporarily down
- Paystack API called only when needed for verification

## Data Model

No new tables. Queries existing:
- `payments` — order payments (reference prefix `PAY-`)
- `vendor_onboarding_payments` — vendor onboarding payments (reference prefix `VOP-`)

Both tables share a nearly identical schema: reference, amount, status, channel, card/momo details, metadata, gateway_response, paid_at, etc.

## Backend

### Controller: `PaymentManagementController`

Admin web controller under the `dashboard` middleware group.

**`index()`** — List all payments
- Queries both `Payment` and `VendorOnboardingPayment` models
- Normalizes into a common shape: id, type (order/vendor_onboarding), reference, user_name, user_email, amount, currency, status, channel, paid_at, created_at, plus related entity info (order number or application ID)
- Filters: status, type (order/vendor_onboarding), search (reference, user name, email), date range (from/to), sorting
- Paginated at 15 per page

**`show(type, id)`** — Single payment detail
- Loads full payment record with all fields (card/momo details, gateway response, metadata, IP, failure reason)
- Loads related entity: order details or vendor application details
- Loads user info

**`verify(type, id)`** — Verify against Paystack (POST)
- Calls Paystack `GET /transaction/verify/:reference`
- Returns full Paystack response to frontend
- Does NOT auto-update local state

**`sync(type, id)`** — Sync local state (POST)
- Runs existing verification logic: `VendorOnboardingPaymentService::verifyPayment()` or `PaystackService::verifyTransaction()`
- Updates payment record + related entity (order payment status or vendor application `payment_completed`)
- Returns updated state

### Routes

```
GET    /dashboard/payments                    → PaymentManagementController@index
GET    /dashboard/payments/{type}/{id}        → PaymentManagementController@show
POST   /dashboard/payments/{type}/{id}/verify → PaymentManagementController@verify
POST   /dashboard/payments/{type}/{id}/sync   → PaymentManagementController@sync
```

`{type}` is either `order` or `vendor-onboarding`.

### Sidebar Navigation

New "Payments" item under the Financial group:

```
Financial
  ├── Commission Statistics
  ├── Vendor Payouts
  ├── All Transactions
  ├── Payments              ← NEW
  └── Vendor Onboarding
```

## Frontend

### List Page: `resources/js/pages/payments/index.tsx`

Follows existing patterns from the orders index page.

**Top bar controls:**
- Search input (300ms debounce) — searches reference, user name, email
- Status filter dropdown: All, Pending, Success, Failed, Abandoned
- Type filter dropdown: All, Order Payments, Vendor Onboarding
- Date range: From / To date pickers
- Sort by: Created date (default), Amount, Status

**Table columns:**

| Reference | User | Type | Amount | Status | Channel | Date |
|-----------|------|------|--------|--------|---------|------|
| VOP-D62N... | Cassie Noel | Vendor Onboarding | GHS 1.00 | pending | — | Mar 15 |
| PAY-X8K2... | John Doe | Order #ORD-123 | GHS 250.00 | success | Mobile Money | Mar 14 |

- Status: colored badges (green=success, yellow=pending, red=failed, gray=abandoned)
- Type: "Vendor Onboarding" or clickable order number
- Clickable rows navigate to detail page
- Pagination at bottom

### Detail Page: `resources/js/pages/payments/show.tsx`

Four card sections:

**Card 1: Payment Overview**
- Reference, status badge, amount, currency
- Created at, paid at
- Channel + method (e.g. "Mobile Money — MTN, 0542441224" or "Card — Visa ****4081")

**Card 2: User & Related Entity**
- User name, email, phone
- Order payment: order number (link), order status
- Vendor onboarding: application ID (link), application status, vendor tier

**Card 3: Technical Details**
- Gateway response, failure reason
- IP address, Paystack reference
- Metadata (collapsible JSON viewer)

**Card 4: Paystack Verification**
- "Verify on Paystack" button
- Displays formatted Paystack response after clicking
- If status mismatch: highlighted alert ("Paystack reports **success** but local status is **pending**")
- "Sync Local Records" button with confirmation dialog explaining what will be updated
- After sync: success toast, page refreshes with updated data

## UI Components

Reuses existing project components:
- Material-UI: Box, Typography, Chip (for status badges)
- Custom: Button, Card, Input, Select, Dialog (for confirmation)
- Icons: lucide-react
- Navigation: Inertia `router.visit()` / `<Link>`

## Testing

Feature tests for:
- Index page with filtering/search/pagination
- Show page data loading
- Verify endpoint (mocked Paystack response)
- Sync endpoint (verifies local DB updates)
- Authorization (only admin/super_admin access)
