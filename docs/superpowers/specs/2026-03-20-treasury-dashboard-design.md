# Treasury Dashboard — Design Spec

**Date:** 2026-03-20
**Status:** Draft
**Author:** Claude (brainstorming with Ashley)

## Problem

The platform owner frequently visits the Paystack dashboard to check incoming funds (onboarding fees, commissions, subscriptions, boostings, ads payments) and manually transfer them to the company bank account. This is tedious and should be handled from within the admin dashboard.

## Solution

A super-admin-only "Treasury" page in the admin dashboard with a tabbed layout providing full visibility into Paystack transactions, settlements, balance, and the ability to transfer funds to a configurable company bank account — eliminating the need to visit Paystack's dashboard.

## Access Control

- **Super admins only** — enforced via an inline `abort_unless(auth()->user()->role === 'super_admin', 403)` check in the controller, since the existing `EnsureUserRole` middleware returns JSON responses which do not work for Inertia web routes. The routes themselves live inside the existing `auth` + `dashboard` middleware group.
- Sidebar item: "Treasury" positioned after "Content Management". The sidebar currently shares a single block for `admin` and `super_admin` roles — Treasury must be conditionally appended only when `role === 'super_admin'`.

---

## Architecture & Data Flow

```
Super Admin Browser
    |
    v
Inertia Page (treasury/index.tsx)
    |  tabs: Overview | Transactions | Settlements | Transfers
    |
    v
TreasuryController (web routes, super_admin gate check)
    |
    |-- Reads: PaystackService -> Laravel Cache (10 min TTL) -> Paystack API
    |
    |-- Writes (transfers): PaystackService -> Paystack API directly (no cache)
    |
    +-- Company Bank Account: CompanyBankAccount model <-> DB
    |
    +-- Audit: TreasuryTransfer model <-> DB (local record of every transfer)
```

### Data Strategy: Hybrid (Cache + Real-time)

- All Paystack read calls go through Laravel cache with 10-minute TTL.
- Writes (transfers) bypass cache and invalidate relevant cache keys after completion.
- A "Refresh" button lets the super admin bust the cache on demand.
- Transfers hit the Paystack API directly in real-time.

---

## Routes

All routes registered inside the existing dashboard route group (`auth` + `dashboard` middleware), **before the SPA catch-all route** (`/{any?}`) to avoid being swallowed by it. Super admin access is enforced within the controller, not via route middleware.

```
GET  /dashboard/treasury                     -> TreasuryController@index
GET  /dashboard/treasury/transactions        -> TreasuryController@transactions
GET  /dashboard/treasury/settlements         -> TreasuryController@settlements
GET  /dashboard/treasury/transfers           -> TreasuryController@transfers

POST /dashboard/treasury/refresh             -> TreasuryController@refresh

GET  /dashboard/treasury/bank-account        -> TreasuryController@bankAccount
POST /dashboard/treasury/bank-account        -> TreasuryController@saveBankAccount
POST /dashboard/treasury/bank-account/verify -> TreasuryController@verifyBankAccount

POST /dashboard/treasury/transfer              -> TreasuryController@initiateTransfer
POST /dashboard/treasury/transfer/finalize    -> TreasuryController@finalizeTransfer
POST /dashboard/treasury/transfer/resend-otp  -> TreasuryController@resendTransferOtp
```

**Rate limiting:** The `POST /dashboard/treasury/transfer` endpoint is rate-limited to 5 requests per hour per user, using a named rate limiter registered in `AppServiceProvider::boot()` via `RateLimiter::for('treasury-transfer', ...)` and applied as route middleware `throttle:treasury-transfer`.

### Why separate tab routes?

Each tab hits different Paystack endpoints. Loading them all at once would be slow even with caching. Separate routes mean we only fetch data for the active tab. Inertia's `<Link>` with `preserveState` keeps the tabbed experience seamless.

### Controller

Single `TreasuryController` with methods per tab plus transfer actions. Each tab method:
1. Checks `abort_unless(auth()->user()->role === 'super_admin', 403)`
2. Returns `Inertia::render()` to `treasury/index` with:
   - A `tab` prop (overview/transactions/settlements/transfers)
   - Tab-specific data props
   - Paystack balance is fetched only on the **Overview** and **Transfers** tabs (where it is displayed). Other tabs do not incur the extra API call.
   - Company bank account info is passed only on the **Transfers** tab.

### Form Requests

- `SaveCompanyBankAccountRequest` — validates `account_number` (required, string), `bank_code` (required, string), `account_name` (required, string)
- `InitiateTransferRequest` — validates `amount` (required, numeric, min:0.01). The balance check is done as business logic in the controller (not in validation) to avoid an extra Paystack API call during request validation.
- `FinalizeTransferRequest` — validates `transfer_code` (required, string), `otp` (required, string)
- `ResendTransferOtpRequest` — validates `transfer_code` (required, string)

---

## PaystackService Extensions

### New methods (cached reads):

| Method | Paystack Endpoint | Description |
|---|---|---|
| `listTransactions(filters)` | `GET /transaction` | List transactions with `from`, `to`, `status`, `perPage`, `page` |
| `getTransactionTotals(filters)` | `GET /transaction/totals` | Aggregate stats with date range. Returns: `total_transactions`, `total_volume`, `pending_amount`, `unique_customers` |
| `listSettlements(filters)` | `GET /settlement` | List settlements with `from`, `to`, `perPage`, `page` |
| `listTransfers(filters)` | `GET /transfer` | List transfers with `from`, `to`, `perPage`, `page` |
| `getBalanceLedger()` | `GET /balance/ledger` | Detailed balance history |
| `resendTransferOtp(transferCode)` | `POST /transfer/resend_otp` | Resend OTP for a pending transfer. Request body: `{ "transfer_code": "TRF_xxx" }` |

### Existing methods reused as-is:

- `checkBalance()` — Paystack balance
- `resolveAccountNumber()` — bank account verification
- `createTransferRecipient()` — transfer recipient creation
- `initiateTransfer()` — initiate transfer (note: takes amount in **pesewas**, controller must convert GHS input x 100)
- `finalizeTransfer()` — OTP finalization
- `getBanks()` — bank list for dropdown (use this one, not the duplicate `listBanks()`)

### Caching strategy:

The application uses the `database` cache driver, which does not support wildcard key deletion. Following the existing `CacheService` pattern, cache invalidation uses explicit key tracking:

- **Cache key pattern:** `treasury:{endpoint}:{hash_of_filters}` — e.g., `treasury:transactions:a1b2c3`
- **Default TTL:** 10 minutes (600 seconds), defined as `CacheService::TTL_TREASURY`
- **Invalidation approach:** A `TreasuryCacheService` (or methods added to the existing `CacheService`) maintains a registry of known cache key prefixes per section. On invalidation, it iterates and forgets each known key explicitly — matching how `flushProductCaches()` and `flushAdvertisementCaches()` already work.
- **`refresh` action:** Calls `TreasuryCacheService::flushAll()` which forgets all known treasury cache keys.
- **After transfers:** Invalidates balance and transfer-related cache keys only.
- No changes to existing PaystackService methods — new methods added alongside them.

---

## Company Bank Account Model

### Table: `company_bank_accounts`

| Field | Type | Description |
|---|---|---|
| id | bigint (auto-increment) | Primary key (matches existing model convention) |
| account_name | string | Verified name from Paystack |
| account_number | string | Bank account number |
| bank_code | string | Bank code (e.g., "058") |
| bank_name | string | Human-readable bank name |
| paystack_recipient_code | string, nullable | Transfer recipient code from Paystack |
| is_active | boolean, default false | Only one active at a time |
| added_by | foreignId -> users | User who added this account |
| created_at | timestamp | |
| updated_at | timestamp | |

**Factory and seeder** must be created alongside the model per project conventions.

### Key behaviors:

- **Verification before save:** Call `resolveAccountNumber()` to verify. The returned `account_name` is shown to the super admin for confirmation before saving.
- **Recipient creation on save:** After confirmation, call `createTransferRecipient()` and store the `recipient_code`.
- **Single active account:** Only one account can be `is_active = true` at a time. Setting a new one as active deactivates the previous one.
- **Audit trail:** Old accounts are kept (deactivated, not deleted).

### Add/change bank account flow:

1. Super admin enters account number + selects bank from dropdown (populated via `getBanks()`)
2. Backend calls Paystack verify -> returns account name
3. Frontend shows: "Account Name: John Doe -- Is this correct?"
4. Super admin confirms -> backend creates transfer recipient + saves to DB

---

## Treasury Transfer Model (Local Audit Trail)

### Table: `treasury_transfers`

| Field | Type | Description |
|---|---|---|
| id | bigint (auto-increment) | Primary key (matches existing model convention) |
| company_bank_account_id | foreignId | Which bank account received the funds |
| initiated_by | foreignId -> users | Super admin who initiated |
| amount | decimal(12,2) | Amount in GHS |
| amount_in_pesewas | integer | Amount sent to Paystack (amount x 100) |
| paystack_transfer_code | string, nullable | Paystack transfer code |
| paystack_reference | string, unique | Unique reference for idempotency |
| status | enum | pending, otp_required, processing, success, failed, reversed |
| paystack_response | json, nullable | Raw response from Paystack for debugging |
| completed_at | timestamp, nullable | When transfer completed |
| created_at | timestamp | |
| updated_at | timestamp | |

**Factory and seeder** must be created alongside the model.

This model ensures a local audit trail of all treasury transfers even if the Paystack API is unreachable. It is distinct from the existing `PayoutRequest` model which tracks vendor payouts.

### Webhook integration:

The existing `PaystackService::handleWebhook()` dispatches `transfer.success` and `transfer.failed` events to `handleTransferSuccess()` and `handleTransferFailed()`, which currently only query `PayoutRequest` by reference. To support treasury transfers:

- Treasury transfer references use a distinct prefix: `TRS-{ulid}` (vs `PYT-` for payouts).
- The `handleTransferSuccess()` and `handleTransferFailed()` methods are extended to first check if the reference starts with `TRS-`. If so, look up and update the `TreasuryTransfer` record instead of `PayoutRequest`.
- On `transfer.success`: set status to `success`, set `completed_at` timestamp.
- On `transfer.failed`: set status to `failed`, store failure reason in `paystack_response`.
- This branching keeps the existing payout webhook flow untouched.

### DB transaction wrapping:

The transfer initiation flow (create record -> call Paystack -> update record) is intentionally **not** wrapped in a DB transaction. If the Paystack call fails after the `TreasuryTransfer` record is created, the record remains in `pending` status — this serves as an audit trail of attempted transfers. The controller catches the Paystack exception, updates the record to `failed`, and returns the error to the frontend.

---

## Frontend: Treasury Page

**Single Inertia page:** `resources/js/pages/treasury/index.tsx` (lowercase, matching existing convention)

Tab navigation using Inertia `<Link>` with `preserveState`. Each tab is a different route but renders the same page component, switching content based on the `tab` prop.

**Charting library:** The Overview tab requires a charting library (line/bar charts). This is a new frontend dependency and needs approval before implementation. Recommended: `recharts` (lightweight, React-native, widely used).

### Overview Tab

- **Balance card** — large display of current Paystack balance with "Refresh" button
- **Quick stats row** — total transactions, total volume, pending amount, unique customers (from `getTransactionTotals`)
- **Revenue chart** — line chart with Daily/Weekly/Monthly toggle showing transaction volume and amount over time
- **Recent transactions** — last 5-10 transactions as a quick-glance table

### Transactions Tab

- **Filters bar** — date range picker, status dropdown (success/failed/abandoned), payment channel (card/bank/mobile_money), amount range
- **Table columns** — date, reference, customer email, amount, channel, status
- **Pagination** — server-side via Paystack's pagination

### Settlements Tab

- **Date range filter**
- **Table columns** — settlement date, amount, status
- **Pagination**

### Transfers Tab

- **Company bank account card** — shows active account (name, bank, number) with "Change Account" button
- **Transfer form** — amount input **in GHS** with "Use Full Balance" shortcut, shows current balance, "Transfer" button. The controller converts GHS to pesewas (x 100) before calling Paystack.
- **OTP modal** — appears after initiation, input field for OTP + "Confirm" button + "Resend OTP" link
- **Transfer history table** — from local `TreasuryTransfer` model: date, amount, status, reference, with pagination

### Loading states:

Skeleton placeholders on tab switch (since each tab fetches fresh data via Inertia visit).

### Sidebar update:

In `app-sidebar.tsx`, the admin/super_admin nav block (line 46) builds an `items` array and returns it at line 169. Insert the following conditional **between the "Content Management" item (line 166) and `return items` (line 169)**:

```
if (role === 'super_admin') {
    items.push({
        title: 'Treasury',
        href: '/dashboard/treasury',
        icon: Vault, // or Landmark from lucide-react
    });
}
```

---

## Transfer Flow

1. Super admin sees current Paystack balance on Transfers tab
2. Enters custom amount (in GHS) or clicks "Use Full Balance"
3. Clicks "Transfer" -> `POST /dashboard/treasury/transfer`
4. Controller validates amount > 0, converts to pesewas, checks amount <= Paystack balance
5. Creates a `TreasuryTransfer` record with status `pending`
6. Calls `PaystackService::initiateTransfer()` with pesewas amount and active bank account's `recipient_code`
7. Updates `TreasuryTransfer` with Paystack transfer code, status `otp_required`
8. Returns transfer code to frontend -> OTP modal shown
9. Paystack sends OTP to the account owner
10. Super admin enters OTP -> `POST /dashboard/treasury/transfer/finalize`
11. Backend calls `PaystackService::finalizeTransfer()`
12. Updates `TreasuryTransfer` status to `processing` (Paystack processes async)
13. Paystack webhook (`transfer.success` / `transfer.failed`) updates the `TreasuryTransfer` status to `success` or `failed`
14. Cache invalidated, transfer history + balance refreshed
15. Success toast shown

---

## Error Handling & Edge Cases

### Paystack API failures:
- If Paystack is down, show cached data (if available) with a "Data may be stale" banner and timestamp of last successful fetch.
- If no cached data exists, show an error state with "Retry" button.
- Transfer failures show clear error messages from Paystack's response.

### Transfer edge cases:
- **Amount exceeds balance** -> controller rejects before hitting Paystack API.
- **No active bank account** -> transfer form disabled with "Set up bank account first" prompt.
- **OTP expired** -> show message + "Resend OTP" button that calls `POST /dashboard/treasury/transfer/resend-otp` (which calls `PaystackService::resendTransferOtp(transferCode)`).
- **Invalid recipient code** (e.g., bank account closed) -> catch error, prompt to re-verify account.

### Concurrency:
- If two super admins initiate transfers simultaneously, Paystack handles this (rejects if balance insufficient for the second).

---

## Testing Plan

### Test classes:

**`TreasuryAccessTest`** — access control:
- Super admin can access all treasury routes
- Admin gets 403 on all treasury routes
- Other roles (vendor, customer, etc.) get 403
- Unauthenticated users get redirected to login

**`TreasuryOverviewTest`** — overview tab:
- Displays balance and transaction totals from mocked Paystack responses
- Handles Paystack API failure gracefully (shows cached/error state)
- Refresh action clears cache

**`TreasuryTransactionsTest`** — transactions tab:
- Lists transactions with pagination
- Filters by date range, status, channel
- Handles empty results

**`TreasurySettlementsTest`** — settlements tab:
- Lists settlements with pagination
- Filters by date range

**`CompanyBankAccountTest`** — bank account management:
- Verify bank account via Paystack (mocked)
- Save bank account creates transfer recipient
- Only one account can be active at a time
- Changing active account deactivates previous

**`TreasuryTransferTest`** — transfer flow:
- Initiate transfer with valid amount
- Reject transfer when amount exceeds balance
- Reject transfer when no active bank account
- Finalize transfer with OTP
- Resend OTP
- Local TreasuryTransfer record is created and updated
- Rate limiting enforced (6th request in an hour is rejected)

All tests use mocked Paystack API responses via `Http::fake()`.

---

## Out of Scope (v1)

- CSV/PDF export of transactions
- Automated/scheduled transfers
- Multi-currency support
- Notification/alerts when balance exceeds threshold
- Subscriptions, boostings, ads breakdown (these features don't exist yet on the backend)

These can be added in future iterations as the platform grows.
