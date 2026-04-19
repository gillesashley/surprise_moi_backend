# Field Agent Dashboard — Design Spec

**Date:** 2026-04-19
**Branch:** `feat/field-agent-dashboard`
**Status:** Approved, pending implementation plan

## 1. Goal

Give a logged-in field agent a single page that answers, at a glance: *How many vendors have I onboarded, where are they in the approval funnel, how much have I earned, and am I on pace against my target?* The dashboard also surfaces the primary action an agent takes — registering a new vendor in person — as the most prominent CTA.

## 2. Non-Goals

- Commission calculation logic (no Earning rows are created by this work — we only read existing ones).
- Vendor verification submission (photos / video / GPS). Separate sub-spec.
- Vendor pipeline page (full list + filters). A "Recent vendors" row on the dashboard is enough; a standalone pipeline page is a later sub-spec.
- Points widget — dropped from v1. Existing `ReferralPointTransaction` is for non-cash earners; field agents earn cash commissions and do not accrue points. If that changes later the widget is a 5-line add.
- Daily/weekly/monthly performance *charts*. We show counts and a target progress bar only.
- Profile & Settings page. Separate sub-spec.
- Admin-side agent performance dashboards. Separate sub-spec.

## 3. Scope — Existing vs. New

**Already in the codebase (reused as-is):**

- `role = 'field_agent'` on `users`; `EnsureUserRole` + `EnsureDashboardAccess` middleware gating `/field-agent/*`.
- `FieldAgentDashboardController@index` and `resources/js/Pages/field-agent/dashboard.tsx` — the current dashboard page and its controller are the starting points for this work.
- `Earning` model + `earningsSummary` computation on the existing controller — reused for the Earnings card.
- Targets system (`User::activeTargets()`) — reused for the Target card.
- `PayoutRequest` model + `/field-agent/payouts` flow — the `Request payout` button links here, nothing new needed.
- `VendorApplication` model, status enum (pending / under_review / approved / rejected), and public vendor registration service — reused by the new in-portal registration flow.

**New in this work:**

- A `referrer_field_agent_id` foreign key on `vendor_applications` — the link that makes every vendor-count tile possible.
- A new controller `FieldAgentVendorRegistrationController` (`create` + `store`) that lets a logged-in agent register a vendor from within the field-agent portal, automatically attributing the vendor to the agent.
- A Form Request `StoreFieldAgentVendorRequest` that reuses the rules from the existing public vendor-registration Form Request (via extension or a shared trait — whichever matches existing conventions).
- Two new routes under the existing `role:field_agent` middleware group: `GET /field-agent/vendors/create`, `POST /field-agent/vendors`.
- A new Inertia page `resources/js/Pages/field-agent/vendors/create.tsx` that hosts the registration form inside the agent layout.
- An update to `FieldAgentDashboardController@index` that adds a `period` request param, vendor-stats aggregation, and a recent-vendors list to the Inertia payload.
- A full rewrite of `resources/js/Pages/field-agent/dashboard.tsx` to the layout defined in §4.2.

**Explicitly untouched:**

- Public vendor registration path. Existing visitors who register a vendor directly continue to do so; their `referrer_field_agent_id` stays `null`.
- Existing field-agent pages at `/field-agent/targets`, `/field-agent/earnings`, `/field-agent/payouts`, `/field-agent/verification` — links from the dashboard point to them unchanged.
- Admin-side vendor review. Admins continue to approve/reject `vendor_applications` exactly as they do today.

## 4. Architecture

### 4.1 Data model

**Migration** `add_referrer_field_agent_id_to_vendor_applications_table`:

```php
Schema::table('vendor_applications', function (Blueprint $table) {
    $table->foreignId('referrer_field_agent_id')
        ->nullable()->after('id')
        ->constrained('users')->nullOnDelete();
    $table->index('referrer_field_agent_id');
});
```

No backfill — pre-existing rows remain `null` (unattributed). Deleting the agent `User` nulls the FK but keeps the vendor record.

**Model updates** on `VendorApplication`:

- Add `referrer_field_agent_id` to `$fillable`.
- Add relationship `referrerFieldAgent(): BelongsTo` → `User`.

### 4.2 Page layout

Single page, three rows under a header bar. All period-sensitive values are driven by a `period` query parameter with values `today | week | month`, default `week`.

**Header bar**
- Greeting: `Good morning, {first_name}` (time-of-day aware).
- Period toggle: segmented control `Today | This Week | This Month`, updates URL param.
- Primary CTA: `[+ Register new vendor]` → `/field-agent/vendors/create`.

**Row 1 — Vendor pipeline (four KPI tiles)**

| Tile | Value | Filter |
| --- | --- | --- |
| Total Vendors | lifetime count | none |
| Pending | count where `status IN (pending, under_review)` | `created_at` ∈ period |
| Approved | count where `status = approved` | `created_at` ∈ period |
| Rejected | count where `status = rejected` | `created_at` ∈ period |

All four are scoped to `referrer_field_agent_id = auth()->id()`.

**Row 2 — Earnings + Target**
- **Earnings card (wide, left):** `Total earned · Pending · Available` with a `Request payout` button that links to `/field-agent/payouts`. Values come from the existing `earningsSummary` computation, unchanged.
- **Target card (wide, right, conditional):** rendered only when `activeTarget` is non-null. Shows progress bar `X / Y vendors this week` plus a remaining-days sub-label. Values come from the existing targets computation, unchanged.

**Row 3 — Recent vendors**
- List of the 5 most recently-created vendor applications attributed to the agent.
- Columns: business name, date (relative), status chip, `View` link.
- Below the list: `See all vendors →` disabled/hidden link (the full pipeline page is out of scope; placeholder for the next sub-spec).

### 4.3 Controller payload

`FieldAgentDashboardController@index(Request $request)` returns this Inertia payload shape:

```php
[
    'agent' => ['id' => int, 'first_name' => string],
    'period' => 'today' | 'week' | 'month',
    'vendorStats' => [
        'total' => int,     // lifetime, ignores period
        'pending' => int,   // in-period
        'approved' => int,  // in-period
        'rejected' => int,  // in-period
    ],
    'earningsSummary' => [ /* unchanged shape */ ],
    'activeTarget' => null | [
        'current' => int,
        'goal' => int,
        'endsAt' => string,   // ISO-8601
    ],
    'recentVendors' => [
        ['id' => int, 'business_name' => string, 'status' => string, 'created_at' => string],
        // up to 5
    ],
]
```

Vendor stats are computed with a single query using `selectRaw` with conditional counts, so the page is O(1) round-trips regardless of how many vendors an agent has.

Period is parsed off the request with a whitelist (`today|week|month`). Any other value falls back to `week`. Period boundaries use the authenticated user's timezone if one is set on the model, else UTC.

### 4.4 New agent-facing vendor registration flow

A new controller pair lets an agent register a vendor while sitting with them in person. This is the only place in the codebase that writes a non-null `referrer_field_agent_id`.

**`FieldAgentVendorRegistrationController`**

- `create(): Response` — renders `resources/js/Pages/field-agent/vendors/create.tsx` inside the field-agent layout. The form is the same fields as the public vendor registration form; no new inputs.
- `store(StoreFieldAgentVendorRequest $request): RedirectResponse` — delegates to the existing vendor registration service, passing `referrer_field_agent_id = auth()->id()` as an additional argument. On success, redirects to `/field-agent/dashboard` with a success flash.

**`StoreFieldAgentVendorRequest`**

Reuses the validation rules from the public `StoreVendorApplicationRequest` (or equivalent). Implementation pattern follows whatever the codebase already does for shared rule sets (inheritance, shared trait, or static `rulesFor()` helper — resolved during plan writing).

**Authorization:** the routes are inside the existing `role:field_agent` middleware group. No additional policy required; any authenticated agent can register a vendor.

### 4.5 Frontend

- Rewrite `resources/js/Pages/field-agent/dashboard.tsx` to match §4.2, using existing shared components (cards, status chips, progress bars) that other agent pages already use.
- New page `resources/js/Pages/field-agent/vendors/create.tsx` — the in-portal vendor registration form. Uses Inertia `<Form>` (per project v2 conventions) and the existing agent layout.
- Period toggle uses `router.visit('/field-agent/dashboard', { only: ['vendorStats'], data: { period } })` so only the affected prop re-fetches (Inertia v2 partial reload).
- The `[+ Register new vendor]` CTA is a `<Link>` to the new create route.

## 5. Testing

PHPUnit feature tests, minimum set to prove the critical invariants:

**`FieldAgentDashboardTest`**

1. `test_stats_include_only_vendors_attributed_to_current_agent` — seeds vendors for agent A, agent B, and a null-referrer vendor; logs in as A; asserts only A's vendors appear in tile counts.
2. `test_period_filter_scopes_pending_approved_rejected_counts` — seeds vendors across dates straddling today/week/month boundaries; toggles `?period=today|week|month`; asserts correct counts.
3. `test_total_vendors_ignores_period_filter` — verifies the `total` tile value is constant as the period toggle changes.
4. `test_active_target_card_omitted_when_no_active_target` — agent with no active target sees `activeTarget = null` in the Inertia payload.
5. `test_recent_vendors_returns_last_five_in_reverse_chronological_order`.
6. `test_invalid_period_falls_back_to_week`.

**`FieldAgentVendorRegistrationTest`**

7. `test_store_persists_vendor_with_referrer_field_agent_id_set_to_current_agent` — the core invariant of the whole design.
8. `test_non_field_agent_cannot_access_register_vendor_route` — middleware gate.
9. `test_store_delegates_to_existing_vendor_registration_service` — asserts vendor is created with the same side-effects (notifications, files, etc.) as the public path, minus only the change in `referrer_field_agent_id`.

All tests use existing factories. No DB mocks; tests hit the real database per the project's testing convention.

## 6. Risks & Open Questions

- **Timezone of period boundaries.** If agents operate in a single timezone this is trivial; if users can set their own timezone we need the period filter to use it. Resolved during plan writing by inspecting `User` for a timezone column.
- **Vendor registration Form Request rule sharing.** Two Form Requests (public + in-portal) must share the same rules and stay in sync. The exact sharing mechanism (parent class, trait, static helper) will match whatever pattern the codebase already uses for similar duplicated validation — resolved during plan writing.
- **Race between period toggle and partial reload.** Rapid clicking the period toggle could stack Inertia reloads; Inertia v2 cancels in-flight requests with the same `only` signature, so this is expected to be a non-issue, but worth a smoke test on slow connections.

## 7. Out of Scope — Followup Sub-Specs

The following come from the original brief but are not in this spec and each deserves its own design doc:

1. Full vendor pipeline page (list, filters, bulk actions).
2. Vendor verification submission module (photos / video / GPS / decision).
3. Commission calculation — the trigger that creates `Earning` rows when an agent's vendor is approved or completes its first order.
4. Agent profile & settings page.
5. Admin-side agent performance dashboards and vendor-verification review queue.
