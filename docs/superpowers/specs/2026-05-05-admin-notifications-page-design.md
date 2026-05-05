# Admin Notifications Page — Design

**Date:** 2026-05-05
**Branch:** `feat/admin-notifications-page`
**Status:** Approved for implementation

## Goal

A read-only system-wide activity feed for admins and super-admins covering vendor onboarding, tier upgrades, and field-agent applications. Visible identically to every admin/super-admin — not tied to any individual user.

## Non-goals

- Per-admin unread/read state
- Push or real-time updates
- Bell-icon unread counter in the topbar
- Search, exports, or bulk actions

These can be added later by switching the backend to Approach B (event-sourced feed table) without changing the page contract.

## Audience & access

- Roles: `admin`, `super_admin`
- Routes are mounted under the existing dashboard/admin middleware stack used by `vendor-applications`, etc. Non-admins → 403 via existing role guard.

## Source of truth (Approach A — derived feed)

Each row is derived from existing domain records. No new tables, no new listeners, no per-user state.

| Source model | Event types | Timestamp used | Notes |
|---|---|---|---|
| `VendorApplication` | `submitted`, `paid`, `approved`, `rejected`, `flagged`, `flag_reminded`, `flag_expired` | `submitted_at`; payment row's `created_at` (joined from `vendor_onboarding_payments`); `updated_at` for approved/rejected (terminal); `flagged_at`; `flag_reminder_sent_at`; `flag_expired_alert_sent_at` | Approved/rejected use `updated_at` as a sufficient proxy because these are terminal transitions. If exact provenance becomes required, add `approved_at`/`rejected_at` columns later. |
| `TierUpgradeRequest` | `submitted`, `paid`, `approved`, `rejected` | `created_at`, `payment_verified_at`, `reviewed_at` (split by `status`) | |
| `FieldAgentApplication` | `submitted`, `approved`, `rejected` | `created_at`, `reviewed_at` (split by `status`) | |

All referenced columns are present on `vendor_applications` as of 2026-05-01 (see `2026_05_01_000000_add_flagging_to_vendor_applications_table.php`).

## Unified row shape

Every source maps to:

```php
[
    'id' => string,           // stable composite, e.g. "vendor_application:123:submitted"
    'category' => 'vendor_onboarding' | 'tier_upgrade' | 'field_agent',
    'type' => string,         // submitted | paid | approved | rejected | flagged | flag_reminded | flag_expired
    'occurred_at' => string,  // ISO 8601
    'actor' => ['id' => int, 'name' => string] | null, // applicant/vendor
    'subject' => [
        'id' => int,
        'type' => 'vendor_application' | 'tier_upgrade_request' | 'field_agent_application',
        'label' => string,    // e.g. "John Doe — Tier 1 (Business)"
    ],
    'action_url' => string,   // deep link to the relevant detail page on the dashboard
]
```

The `id` is stable across requests so the frontend can dedupe across pagination boundaries.

## Backend

### `App\Services\AdminNotificationFeedService`

One service, one public method:

```php
public function feed(
    array $categories,   // subset of ['vendor_onboarding','tier_upgrade','field_agent'], empty = all
    int $perPage = 30,
    int $page = 1,
): LengthAwarePaginator;
```

Internals — one private method per source. Each returns a `Collection<array>` of unified rows. The public method merges, sorts by `occurred_at` desc with `id` as the deterministic tiebreaker, and slices for the requested page.

Performance is acceptable up to a few thousand rows per source for v1. If the dataset grows materially, switch to Approach B.

### `App\Http\Controllers\AdminNotificationFeedController`

```php
public function index(Request $request): InertiaResponse;
```

- Validates `category` query param (array or comma-separated string) against the three allowed values
- Validates `page` (positive int)
- Calls the service, passes the paginator + filters into `Inertia::render('notifications/index', [...])`

### Route

```php
Route::get('/dashboard/notifications', [AdminNotificationFeedController::class, 'index'])
    ->middleware(['auth', /* existing admin guard */])
    ->name('admin.notifications.index');
```

Place inside the same admin route group as `vendor-applications`.

## Frontend

`resources/js/pages/notifications/index.tsx`:

- Dashboard layout
- Page title: "Notifications"
- Filter chips row: **All / Vendor Onboarding / Tier Upgrades / Field Agents**. Multi-toggle. State syncs to query string via Inertia router so deep-links work.
- Feed body grouped by date bucket: **Today / Yesterday / Last 7 days / Older**.
- Each row: type icon (left), title (e.g. *"John Doe submitted vendor application"*), subtitle (e.g. "Tier 1 (Business)"), relative time on the right. Whole row clickable → `action_url`.
- Pagination: Inertia v2 `WhenVisible` for "load more" using deferred props. Empty state below the feed when nothing remains.
- Empty state (initial): "No notifications yet."

Icon-per-type mapping lives in a small lookup module so it can be tested independently.

## Sidebar

`resources/js/components/app-sidebar.tsx`: add a top-level entry **Notifications** with the `Bell` icon, positioned **between Dashboard and User Management**. `href` is the new route, no children.

## Tests

### Unit — `Tests\Unit\Services\AdminNotificationFeedServiceTest`

- Given a vendor application that has been submitted and approved, the feed contains exactly two rows for it with the expected types, occurrence times, and action_urls.
- Given a tier upgrade request that's been submitted, paid, and rejected, the feed contains three rows in the expected order.
- Given a field-agent application that's been approved, the feed contains submitted + approved.
- Filters: requesting `category=['vendor_onboarding']` excludes tier and field-agent rows.
- Sort + tie-break: two rows with identical `occurred_at` are returned in a deterministic order (by `id`).
- Pagination: requesting `page=2` with `perPage=2` over 5 rows returns rows 3 and 4 in feed order.

### Feature — `Tests\Feature\AdminNotificationFeedControllerTest`

- Admin gets 200 and an Inertia response with the expected page component and props.
- Super-admin gets 200.
- Customer / vendor / field agent / unauthenticated → 403 / 302 (whatever the existing admin middleware does today — match it).
- `?category=tier_upgrade,field_agent` filters correctly.
- Invalid category value → 422 (validation error).

## Pint & coding-style

Standard project conventions apply (Boost guidelines, Pint `--format agent` before commit).

## Open questions / future work

- If a unified read state ever becomes a requirement, replace the derivation with an `admin_feed_events` table + listeners on existing domain events. The frontend contract above (row shape, route, filter params) does not need to change.
- If the bell icon in the topbar should show an unread count, that requires the same migration.
