# Field Agent Dashboard — Design Spec

**Date:** 2026-04-19
**Branch:** `feat/field-agent-dashboard`
**Status:** Approved, pending implementation plan

## 1. Goal

Give a logged-in field agent a single page that answers, at a glance: *How many vendors have I onboarded, where are they in the approval funnel, how much have I earned, and am I on pace against my target?* The dashboard also surfaces the agent's referral code — the mechanism by which vendors they bring in get attributed to them.

## 2. Non-Goals

- Commission calculation logic (no Earning rows are created by this work — we only read existing ones).
- Vendor verification submission (photos / video / GPS). Separate sub-spec.
- Vendor pipeline page (full list + filters). A "Recent vendors" row on the dashboard is enough; a standalone pipeline page is a later sub-spec.
- Points widget — dropped from v1. Existing `ReferralPointTransaction` is for non-cash earners; field agents earn cash commissions and do not accrue points.
- Performance charts (daily/weekly/monthly trends). We show counts and a target progress bar only.
- Profile & Settings page. Separate sub-spec.
- Admin-side agent performance dashboards. Separate sub-spec.
- Auto-prefilling the agent's referral code from a URL query parameter (e.g. `/register?agent=FA-ABC12345`) during vendor registration. Nice-to-have UX; not required for attribution to work because the existing payment step already accepts the code as user input.

## 3. Scope — Existing vs. New

**Already in the codebase (reused as-is):**

- `role = 'field_agent'` on `users`; `EnsureUserRole` + `EnsureDashboardAccess` middleware gating `/field-agent/*`.
- `FieldAgentDashboardController@index` and `resources/js/Pages/field-agent/dashboard.tsx` — the starting points for this work.
- `Earning` model + `earningsSummary` computation — reused for the Earnings card.
- Targets system (`Target` model, `TargetService`) — reused for the Target card.
- `PayoutRequest` model + `/field-agent/payouts` flow — the `Request payout` button links here.
- `ReferralCode` model with a built-in `'field_agent' => 'FA-'` role prefix and `generateUniqueCode()` helper — reused wholesale for agent code generation.
- `vendor_applications.referral_code_id` + `referral_code_used` columns — already exist; written by `VendorOnboardingPaymentService` when a vendor enters a valid code during the payment step.
- `ReferralCode::incrementUsage()` and validity-check logic — already used by the payment flow.

**New in this work:**

- `FieldAgentApprovalService::approve()` is updated to also create a `ReferralCode` for the newly-approved agent (prefix `FA-`, `influencer_id` = new user id, `is_active = true`, no expiry, no max usages). The code is generated automatically via the model's `boot()` hook.
- An artisan console command `field-agents:backfill-referral-codes` that creates referral codes for any existing approved field agents who don't already have one. Safe to re-run (idempotent).
- An update to `FieldAgentDashboardController@index` that adds a `period` request param, vendor-stats aggregation (by joining through `referral_codes`), recent-vendors list, and the agent's referral code to the Inertia payload.
- A full rewrite of `resources/js/Pages/field-agent/dashboard.tsx` to the layout defined in §4.2, including a "Your referral code" card with copy-to-clipboard and a share link.

**Explicitly untouched:**

- `vendor_applications` schema — no new columns.
- Public vendor registration flow (6-step wizard + payment step). The existing payment step already accepts and validates referral codes and writes `referral_code_id` onto the application. Nothing there changes.
- Existing field-agent pages at `/field-agent/targets`, `/field-agent/earnings`, `/field-agent/payouts`, `/field-agent/verification` — links from the dashboard point to them unchanged.
- Admin-side vendor review. Admins continue to approve/reject `vendor_applications` exactly as they do today.
- The other existing consumers of `ReferralCode` (influencers, bulk-generate UI, payment flow) — their behavior is unchanged; we only add a new type of producer (agent approval).

## 4. Architecture

### 4.1 Data model

**No schema changes.** Attribution already flows through:

```
vendor_applications.referral_code_id → referral_codes.id
referral_codes.influencer_id → users.id
```

A vendor application is "attributed to agent X" when its `referral_code → influencer_id = X`. Because `role = 'field_agent'` users will only ever own codes with the `FA-` prefix, and these users only see their own dashboard, there's no cross-role contamination risk.

**`FieldAgentApprovalService::approve()` change** — inside the existing `DB::transaction`, after the new `User` is created, also create:

```php
$code = new ReferralCode([
    'influencer_id' => $user->id,
    'is_active' => true,
]);
$code->prefix = ReferralCode::getPrefixForRole('field_agent'); // 'FA-'
$code->save();
```

The model's `creating` boot hook auto-generates the code string (e.g. `FA-8KZX2A1Q`) and ensures uniqueness.

**Backfill** — a new artisan command `app/Console/Commands/BackfillFieldAgentReferralCodesCommand.php` iterates over users with `role = 'field_agent'` who have no `ReferralCode` where `influencer_id = user.id`, and creates one per user using the same logic as `approve()`. Idempotent — re-running is a no-op if every agent already has a code. Logged output: `"Created N referral codes for existing field agents."`

### 4.2 Page layout

Single page, three rows under a header bar. All period-sensitive values are driven by a `period` query parameter with values `today | week | month`, default `week`.

**Header bar**
- Greeting: `Good morning, {first_name}` (time-of-day aware).
- Period toggle: segmented control `Today | This Week | This Month`, updates URL param.
- **Referral code card (primary visual element):** `Your code: FA-8KZX2A1Q` with two actions: `[Copy code]` and `[Copy signup link]`. The signup link is `route('vendor-registration.start')` or equivalent — resolved from the existing public vendor onboarding entry point (no agent-specific URL).

**Row 1 — Vendor pipeline (four KPI tiles)**

| Tile | Value | Filter |
| --- | --- | --- |
| Total Vendors | lifetime count attributed to this agent | none |
| Pending | count where `status IN (pending, under_review)` | `created_at` ∈ period |
| Approved | count where `status = approved` | `created_at` ∈ period |
| Rejected | count where `status = rejected` | `created_at` ∈ period |

All four count `vendor_applications` whose `referral_code_id` belongs to any `ReferralCode` owned by the current agent (`influencer_id = auth()->id()`).

**Row 2 — Earnings + Target**
- **Earnings card (wide, left):** `Total earned · Pending · Available` with a `Request payout` button that links to `/field-agent/payouts`. Values come from the existing `EarningService::getUserEarningsSummary($user)`, unchanged.
- **Target card (wide, right, conditional):** rendered only when an active target exists. Shows progress bar `X / Y vendors this week` plus remaining-days sub-label. Values from existing `TargetService::getUserTargetStats($user)`.

**Row 3 — Recent vendors**
- List of the 5 most recently-created vendor applications attributed to the agent.
- Columns: business name (from related User), date (relative), status chip, `View` link.
- Below the list: `See all vendors →` placeholder (the full pipeline page is a followup sub-spec).

### 4.3 Controller payload

`FieldAgentDashboardController@index(Request $request)` returns this Inertia payload shape:

```php
[
    'agent' => ['id' => int, 'first_name' => string],
    'period' => 'today' | 'week' | 'month',
    'referralCode' => [
        'code' => 'FA-8KZX2A1Q',
        'shareUrl' => 'https://dashboard.surprisemoi.com/vendor/register', // existing public URL
    ],
    'vendorStats' => [
        'total' => int,     // lifetime, ignores period
        'pending' => int,   // in-period
        'approved' => int,  // in-period
        'rejected' => int,  // in-period
    ],
    'earningsSummary' => [ /* existing shape */ ],
    'activeTarget' => null | [
        'current' => int,
        'goal' => int,
        'endsAt' => string,   // ISO-8601
    ],
    'recentVendors' => [
        [
            'id' => int,
            'business_name' => string, // from related User.name or VendorProfile
            'status' => string,
            'created_at' => string,
        ],
        // up to 5
    ],
]
```

Vendor stats are computed with a single query joining `vendor_applications` ← `referral_codes` on `referral_code_id = referral_codes.id` filtered by `referral_codes.influencer_id = auth()->id()`, using `selectRaw` with conditional counts so the page is O(1) round-trips regardless of volume.

Period is parsed off the request with a whitelist (`today|week|month`); any other value falls back to `week`. Period boundaries use the application timezone (`config('app.timezone')`).

If the authenticated field agent has no `ReferralCode` yet (shouldn't happen after the backfill runs, but possible if someone is approved after the backfill but before this deploy), the controller lazily creates one using the same logic as `FieldAgentApprovalService`. This keeps the dashboard robust against missing-code edge cases.

### 4.4 Attribution flow (no code changes, documented for clarity)

Unchanged from today, restated so implementers and reviewers share one mental model:

1. Admin approves a field-agent application → `FieldAgentApprovalService::approve()` now also creates a `ReferralCode` for the new agent.
2. Agent logs in and sees their code on the dashboard. They share it (or the signup URL) with a prospective vendor during an in-person meeting.
3. Vendor self-registers via the existing public vendor-registration wizard, using their own device.
4. At the payment step, vendor enters the agent's code. Existing `VendorOnboardingPaymentService` validates it and writes `referral_code_id` + `referral_code_used` to the `vendor_applications` row.
5. Dashboard queries surface that application under the agent's stats.

No changes are needed at any step except (1).

### 4.5 Frontend

- **Rewrite** `resources/js/Pages/field-agent/dashboard.tsx` to match §4.2, using the same shared components other agent pages already use (cards, status chips, progress bar).
- **New component** (colocated with the dashboard page) `ReferralCodeCard.tsx` — shows the code, two copy buttons, and an optional small explainer line ("Share this code with a vendor during registration").
- Period toggle uses `router.visit('/field-agent/dashboard', { only: ['vendorStats', 'recentVendors'], data: { period } })` so only affected props re-fetch (Inertia v2 partial reload).
- No new routes. No new page besides the rewritten dashboard.

## 5. Testing

PHPUnit feature tests, minimum set to prove the critical invariants.

**`FieldAgentApprovalServiceTest`**

1. `test_approving_a_field_agent_application_creates_a_referral_code_for_the_new_user` — after calling `approve()`, assert a `ReferralCode` exists with `influencer_id = newUser.id`.
2. `test_generated_referral_code_uses_the_FA_prefix` — assert the created code starts with `FA-`.
3. `test_generated_referral_code_is_active_and_has_no_expiry_or_max_usages` — assert `is_active = true`, `expires_at = null`, `max_usages = null`.
4. `test_approval_rolls_back_referral_code_on_failure` — if user creation throws inside the transaction, no orphan referral code is left. (Simulated via a DB mock or by causing a later-in-transaction failure.)

**`BackfillFieldAgentReferralCodesCommandTest`**

5. `test_backfill_creates_codes_only_for_agents_without_one` — seed 3 agents, give 1 a code; run command; assert 2 codes created, not 3.
6. `test_backfill_is_idempotent` — run command twice; assert no duplicate codes on second run.

**`FieldAgentDashboardTest`**

7. `test_stats_include_only_vendors_who_used_this_agents_referral_code` — seed vendors attributed to agent A, agent B, and one with no referral code; log in as A; assert counts reflect only A's.
8. `test_period_filter_scopes_pending_approved_rejected_counts` — seed vendors across dates straddling today/week/month boundaries; toggle `?period=today|week|month`; assert correct counts.
9. `test_total_vendors_ignores_period_filter` — the `total` tile stays constant as the period toggle changes.
10. `test_active_target_card_omitted_when_no_active_target` — agent with no active target sees `activeTarget = null` in the Inertia payload.
11. `test_recent_vendors_returns_last_five_in_reverse_chronological_order`.
12. `test_invalid_period_falls_back_to_week`.
13. `test_dashboard_lazily_creates_a_referral_code_when_agent_has_none` — edge case covering the fallback in §4.3.

All tests use existing factories (`User`, `FieldAgentApplication`, `ReferralCode`, `VendorApplication`). Real database, no mocks, per project convention.

## 6. Risks & Open Questions

- **Backfill on existing data.** The `field-agents:backfill-referral-codes` command must be run (manually or via a deploy step) before agents rely on dashboard attribution. The dashboard's lazy-create fallback (§4.3) covers any straggler.
- **Vendor must remember/enter the code manually.** Attribution depends on the vendor entering the code at payment time. If they forget or skip it, the vendor is not attributed to the agent. A followup auto-fill via URL query parameter would close this gap; it's listed in §7.
- **Timezone.** Period boundaries use `config('app.timezone')`. If per-user timezones are ever introduced, this assumption needs revisiting.
- **Existing approved agents' codes become visible on their dashboard as soon as this ships.** Not a risk but worth noting for launch communications.

## 7. Out of Scope — Followup Sub-Specs

1. Full vendor pipeline page (list, filters, bulk actions).
2. Vendor verification submission module (photos / video / GPS / decision).
3. Commission calculation — the trigger that creates `Earning` rows when an agent-attributed vendor is approved or completes their first order.
4. Agent profile & settings page.
5. Admin-side agent performance dashboards and vendor-verification review queue.
6. **Auto-fill agent code from URL query parameter** — extend the public vendor-registration wizard to read `?agent=FA-ABC12345` and pre-fill/lock the code field at the payment step. Improves attribution reliability without changing this spec's data model.
