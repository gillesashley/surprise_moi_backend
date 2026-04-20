# Field Agent Vendor Validation Checklist — Design Spec

**Date:** 2026-04-20
**Branch (spec):** `docs/field-agent-vendor-validation-checklist-spec`
**Branch (impl — TBD):** `feat/field-agent-vendor-validation-checklist`
**Status:** Approved, pending implementation plan

## 1. Goal

Give field agents a structured way to visit live vendors in person, verify that the vendor's claimed identity, location, business, and (where applicable) documents are genuine, and award a time-limited public **"Field Verified"** trust badge when they pass. The goal is customer trust: a shopper browsing the mobile app should be able to see at a glance that a human from our team has physically stood in front of this business and confirmed it is real.

The feature lives entirely inside the field agent's existing dashboard — no separate mobile app, no paper form. Agents run the checklist on their phone's browser while standing at the vendor's premises.

## 2. Non-Goals

- **Blocking new-vendor admin approval.** The existing document-based approval flow stays as-is. Field visits happen *after* admin approval and are a separate, post-approval trust layer.
- **Field agents signing up brand-new vendors in person.** Separate sub-spec. This spec is about validation of vendors who already exist in our system.
- **Offline mode / PWA.** v1 is online-only. Agents must have connectivity to run a visit.
- **Admin-editable checklist templates.** Checklist items are hardcoded in v1. Promotion to admin-managed templates is deliberately deferred (YAGNI).
- **Bulk admin actions** (revoke many badges at once). If that's ever needed, it's an incident and deserves a one-off script, not UI.
- **GPS anti-spoofing detection.** We store the captured coordinates so admin can spot-check, but we don't actively detect spoofed locations in v1.
- **Regional assignment scoping.** All field agents can see all live vendors in v1. Regional scoping is a later enhancement.
- **Analytics surface** (% vendors verified, agent leaderboards, etc.). Existing BI reporting covers this; we don't build a new dashboard.
- **New-vendor web dashboard for the badge.** Vendors only use the mobile app at `C:\dev\surprise_moi`, never the web backend. Their view of their own badge is exposed via API and rendered in the app.

## 3. Scope — Existing vs. New

**Already in the codebase (reused as-is):**

- `role = 'field_agent'` on `users`; `EnsureUserRole` middleware gating `/field-agent/*`.
- `FieldAgentDashboardController` and the existing field agent dashboard page — we add a summary card pointing to the new visits page; otherwise untouched.
- `User` model with `role = 'vendor'` — the live vendor record we're validating.
- `VendorApplication` model — read-only here, used to determine whether a vendor is registered (shows documents items) or unregistered (skips them).
- Existing image-upload pipeline used by `VendorApplication` (Ghana Card, selfie, certs) — reused for the agent's storefront/owner photos.
- The existing mobile-facing API resources that return vendor data to customer-facing endpoints — widened, not replaced.

**New in this work:**

- Two new tables: `vendor_visits`, `vendor_visit_items`.
- Two new cached columns on `users`: `field_verified_at`, `field_verified_until`.
- New controller: `FieldAgentVisitsController` (index, show-vendor, store, update-item, submit).
- New admin controller: `AdminVendorVisitsController` (index, show, override, revoke-badge).
- New service/action class: `CompleteVendorVisit` — pure auto-compute logic, unit-testable without HTTP.
- New observer: keeps the cached columns on `users` in sync with visit outcomes and auto-invalidates badges when the vendor edits critical profile fields.
- New Inertia pages under `resources/js/Pages/field-agent/visits/` (index, show, new) and `resources/js/Pages/admin/vendor-visits/` (index, show).
- New API endpoint for the mobile app: `GET /api/v1/vendor/field-verification`.
- Extension of existing vendor-facing API resources to include `is_field_verified`, `field_verified_until`.

**Explicitly untouched:**

- `vendor_applications` schema, onboarding wizard, payment step, admin review flow for applications.
- Existing field agent pages at `/field-agent/targets`, `/field-agent/earnings`, `/field-agent/payouts`, `/field-agent/verification`, and the dashboard layout itself (we only add a summary card).
- Earnings, targets, referral code, payout flows — field visits are separate from those systems.

## 4. Key Decisions

| Decision | Choice |
|----------|--------|
| When does a visit happen? | After admin approval; periodic post-approval audits. Not a gate. |
| Visit outcome | Single "Field Verified" badge with a fixed expiry. |
| Badge validity | **12 months.** Re-visit required to renew. |
| Which vendors to visit? | Agent picks from a prioritized list (never-verified, expired, expiring-soon, flagged). No admin assignment in v1. |
| Offline mode | None in v1. Online-only. |
| Checklist flexibility | Hardcoded items, stored as structured rows (not JSON) so future promotion to admin-configurable templates is a small follow-up. |
| Auto-compute vs. admin-approves | **Auto-compute on submit.** Admin handles only escalations and failures, plus can override anytime. |
| Escalation signal | Dedicated "Escalate to admin" checkbox on the form; does not depend on parsing notes text. |
| Customer-visible signal | Single green "Field Verified" badge. No score, no tiers. |

## 5. Data Model

### 5.1 `vendor_visits` table

```
id                         uuid, pk
vendor_user_id             fk users (role = vendor), indexed
field_agent_user_id        fk users (role = field_agent), indexed
status                     enum: draft | submitted | passed | failed | revoked
started_at                 timestamp
submitted_at               timestamp, nullable
visit_latitude             decimal(10,7), not null
visit_longitude            decimal(10,7), not null
storefront_photo_path      string, nullable while draft, required on submit
owner_photo_path           string, nullable while draft, required on submit
notes                      text, nullable (general agent notes)
escalated                  boolean, default false
computed_result            enum: passed | failed, nullable until submit
admin_override_result      enum: passed | failed, nullable
admin_override_reason      text, nullable
admin_override_by          fk users, nullable
admin_override_at          timestamp, nullable
badge_issued_at            timestamp, nullable
badge_expires_at           timestamp, nullable
timestamps
```

Indexes: `(vendor_user_id, status)`, `(field_agent_user_id, status)`, `badge_expires_at`.

### 5.2 `vendor_visit_items` table

```
id                         bigint, pk
vendor_visit_id            fk vendor_visits, indexed, cascade on delete
item_key                   string (e.g., "identity.person_matches_ghana_card")
category                   enum: identity | physical | documents | financial
criticality                enum: critical | informational
passed                     boolean, nullable while draft
note                       text, nullable (per-item note)
timestamps
```

Unique constraint: `(vendor_visit_id, item_key)`.

Rows are pre-seeded when the visit starts, so submit-time validation can simply check "are all items non-null?" rather than reconciling against a reference list.

### 5.3 New columns on `users`

```
field_verified_at          timestamp, nullable
field_verified_until       timestamp, nullable
```

These are **denormalized cached** columns for fast customer-facing reads. The authoritative source is the latest `passed` (and non-revoked) `vendor_visits` row. A single observer keeps them in sync — all writes to these columns go through that one path.

**Why cached columns?** The customer-facing storefront and product listings render for thousands of users. We don't want every page load doing a subquery into `vendor_visits`. The tradeoff — two sources of truth — is mitigated by funneling all writes through one observer.

**Badge natural expiry is handled by filtering**, not by a cron/job. Every query that reads badge state filters `WHERE field_verified_until > NOW()`. No scheduled task can get out of sync with what's actually in the DB.

## 6. Checklist Items

Each row becomes a pre-seeded `vendor_visit_items` entry when the visit starts. `category` and `criticality` come from this table:

### 6.1 Identity — all critical

| item_key | Agent's question |
|----------|------------------|
| `identity.person_matches_ghana_card` | Does the person in front of me match the Ghana Card photo on file? |
| `identity.name_matches_records` | Does the name on the physical Ghana Card match the name on file? |

### 6.2 Physical — all critical

| item_key | Agent's question |
|----------|------------------|
| `physical.location_is_real` | Is the claimed business address a real, findable location? |
| `physical.business_name_matches` | Does the business at this address match the business name on file (signage, receipts, etc.)? |
| `physical.business_is_operational` | Is there signage, stock, or active service — a real going concern, not a ghost shop? |

### 6.3 Documents — critical, conditional

Each item is seeded **independently** based on its own precondition. A fully unregistered vendor sees neither; a vendor with only a TIN sees only the TIN item.

| item_key | Seed when | Agent's question |
|----------|-----------|------------------|
| `documents.business_cert_seen` | `VendorApplication.has_business_certificate = true` | Have I seen the physical business certificate, and does it match the file? |
| `documents.tin_seen` | `VendorApplication.tin_number` is non-empty | Have I seen the physical TIN document, and does it match the file? |

### 6.4 Financial — informational

These are logged but do not fail a visit on their own (agent's own phone may be out of credit; vendor's SIM may be dead that day — not strong fraud signals).

| item_key | Agent's question |
|----------|------------------|
| `financial.phone_reachable` | I called the vendor's registered phone; it rang / was answered. |
| `financial.momo_test_received` | I sent a GHS 1 MoMo to the registered mobile money number; it was received. |

### 6.5 Required evidence (not checklist items — enforced as required uploads at submit)

- **Storefront photo** — signage visible.
- **Owner-at-premises photo** — owner holding their Ghana Card.
- **GPS lat/lng** — captured at the "Start visit" click; required to start.

## 7. Auto-compute Rule (`CompleteVendorVisit` action)

Executed server-side on submit. Pure function of the visit + its items; easy to unit-test.

```
1. If escalated == true  → status = submitted
2. Else if:
     all critical items passed == true
     AND storefront_photo_path is present
     AND owner_photo_path is present
     AND GPS is present (always true — enforced at start)
   → status = passed
     badge_issued_at = submitted_at
     badge_expires_at = submitted_at + 12 months
3. Else → status = failed
```

The same action writes the outcome, and a single observer on `VendorVisit` propagates `passed` / `revoked` transitions to the vendor's cached `field_verified_*` columns.

## 8. Workflow & State Machine

```
                             ┌──────────┐
                             │  draft   │  (created on "Start visit")
                             └────┬─────┘
                                  │ submit
              ┌───────────────────┼───────────────────┐
              ▼                   ▼                   ▼
         ┌─────────┐         ┌────────┐          ┌────────┐
         │submitted│         │ passed │          │ failed │
         └────┬────┘         └───┬────┘          └───┬────┘
              │ admin decides    │ admin override    │ admin override
              ▼                  ▼                   ▼
         passed | failed    revoked | (unchanged)   passed | (unchanged)
```

**Happy-path agent flow:**

1. Agent opens field-agent dashboard → taps **Visits** nav item → sees "Needs visit now" list.
2. Agent taps a vendor → vendor detail page shows the claim data (name, address, Ghana Card, selfie, docs) as their on-site reference sheet.
3. Agent taps **Start visit**. Browser requests GPS (required). A `vendor_visits` row is created with `status=draft`, started_at, lat/lng. Items are pre-seeded based on the vendor's registration status.
4. Agent fills the mobile-first form at the vendor's premises: pass/fail per item (auto-saved on click), optional per-item notes (auto-saved on blur), two camera-captured photos (uploaded when attached), general notes, optional "Escalate to admin" checkbox.
5. Agent taps **Submit visit**. Server validates completeness, auto-compute runs, observer writes cached columns if `passed`.
6. Confirmation screen: outcome, badge expiry (if passed), "Back to visits list".

**Off-happy-path:**

- Draft abandoned mid-fill → stays as `draft`; resume slot appears on the agent's visits index. No auto-expiry in v1.
- One draft per (agent, vendor) at a time — starting again resumes the existing draft instead of creating a duplicate.
- Two different agents starting drafts on the same vendor is allowed; whichever submits first updates the badge; the later submission still records a valid visit.

## 9. Permissions / Visibility

| Role | What they see |
|------|---------------|
| Field agent | Their own visits only; all live vendors in the visits list (no regional scoping in v1). |
| Admin | All visits, all vendors. Can override outcomes and revoke active badges. |
| Vendor (the audited party, via mobile app) | Their own current badge status + a timeline of their past visit outcomes. **Not** the agent's notes, per-item pass/fail, photos, or GPS. |
| Customer (via mobile app) | The public "Field Verified" badge on vendor storefronts and product cards. Nothing else. |

## 10. Field Agent Dashboard UX

Three new pages inside the existing `/field-agent/*` route group, gated by `role:field_agent` middleware.

### 10.1 `/field-agent/visits` — index

Four stacked sections, each a ranked list. The first three are actionable; the fourth is collapsed by default.

1. **Needs visit now** — never-verified + badge-expired + admin-flagged. Ranked by "never-verified first, then oldest expiry first". Each row: business name, address, last visit date (or "never"), badge status, distance from the agent's current location (if GPS available), **Start visit** button.
2. **Expiring soon** — badge expires within 30 days.
3. **Resume drafts** — the agent's open `draft` visits. Tap to continue from the same form state.
4. **Recently verified** (collapsed) — badge active, > 30 days to expiry. Reference only.

On the existing `/field-agent/dashboard` page, add a small summary card at the top: *"N vendors need a visit"* with a button to the visits index.

### 10.2 `/field-agent/visits/{vendor}` — vendor detail (pre-visit reference)

- Vendor claim data: business name, address, phone, mobile money number + provider, registration status, TIN (if any), social handles.
- Tap-to-enlarge documents on file: Ghana Card front, Ghana Card back, selfie, business certificate (if registered), TIN document (if any).
- Previous visits timeline: "Visited by Agent X on DATE → passed / failed / revoked, expires Y."
- Large **Start visit** button.

### 10.3 `/field-agent/visits/{vendor}/new` — visit form

Mobile-first, single page, sections collapsible.

- Sticky top strip: vendor name + "GPS captured ✓".
- Four sections in order: Identity (2 items), Physical (3 items), Documents (0 or 2), Financial (2). Each item = pass/fail toggle + optional note field.
- Required uploads block: two camera-first tiles (`<input type="file" accept="image/*" capture="environment">`) — **Storefront photo** and **Owner at premises (holding Ghana Card) photo**.
- General notes textarea.
- "Escalate to admin" checkbox with helper text: *"Tick this if something feels off but you can't prove it. An admin will decide."*
- Sticky bottom: **Submit visit** button. Disabled until all items have pass/fail, both photos uploaded, and GPS present. Disabled state shows a tooltip listing what's still missing.

After submit: confirmation screen with outcome, badge expiry (if passed), and "Back to visits list".

**Auto-save behavior:** every item pass/fail toggle PATCHes the item row. Notes debounce-save on blur. Photos upload the moment they're attached. Net effect: a browser reload loses at most the last ~30 seconds of keystrokes.

## 11. Admin Screens

Additions to the existing admin surface; no new workspace.

### 11.1 `/admin/vendor-visits` — index

Tabs:
- **Needs review** — `status=submitted` (escalated). Default tab.
- **Recent failures** — `status=failed` in the last 30 days.
- **All visits** — full log with search by vendor, agent, date, status.

### 11.2 `/admin/vendor-visits/{visit}` — detail

- Vendor info header with a link to the vendor's profile.
- Agent info with their historical pass/fail rate.
- Visit metadata: started, submitted, GPS coords on an embedded map preview, duration.
- Full checklist with every item's pass/fail and the agent's per-item notes.
- Both uploaded photos (full size).
- General notes + escalation flag.
- **Admin action panel**: current computed result (read-only); override buttons (**Mark passed** / **Mark failed** / **No override**) requiring a reason; **Revoke active badge** button (with confirmation) if the visit currently holds the vendor's active badge.

### 11.3 Vendor profile page (existing)

Add a "Field verification" panel: current badge status + expiry, last 3 visits as a timeline, link to the full visits list, **Revoke badge** button.

### 11.4 Field agent profile / list page

Add two columns: **visits completed (last 30d)** and **pass rate**.

### 11.5 Notifications

- **`submitted` (escalated)** → email to admins + in-app notification.
- **`failed`** → in-app notification only. Failures are expected; no email spam.
- **Badge revocation by admin** → email to the vendor ("your Field Verified status has been revoked"), activity log entry.

## 12. Mobile App API Changes (`C:\dev\surprise_moi`)

### 12.1 Vendor resource widened (existing endpoints)

All existing mobile-facing endpoints that return vendor data (product listings, vendor storefront, search) get these two new fields added to the `VendorResource` payload:

```
is_field_verified       bool  (derived: field_verified_until > now())
field_verified_until    timestamp | null
```

Customer-facing UI on the mobile app uses `is_field_verified` to render the public badge.

### 12.2 New endpoint — vendor's own badge view

```
GET  /api/v1/vendor/field-verification
Auth: Sanctum, role = vendor, scoped to the authenticated vendor
```

Returns:
```json
{
  "is_field_verified": true,
  "field_verified_at": "2026-04-01T10:00:00Z",
  "field_verified_until": "2027-04-01T10:00:00Z",
  "visits": [
    {
      "id": "uuid",
      "visited_at": "2026-04-01T10:00:00Z",
      "outcome": "passed",
      "badge_expires_at": "2027-04-01T10:00:00Z"
    }
  ]
}
```

**Not** included in this payload: agent identity or notes, per-item pass/fail, photos, GPS. The vendor gets outcome-level transparency, nothing more.

## 13. Error Handling & Edge Cases

**Hard-fail (flow refuses):**
- GPS permission denied → cannot start visit. Explicit message: *"Location is required to verify a visit happened in person."*
- Vendor is not approved / not active → *"This vendor isn't approved yet — there's nothing to verify."*
- Draft for a vendor whose account was later deactivated → resume shows *"This vendor is no longer active"*, offers to discard the draft.

**Soft-fail (flow recovers):**
- Photo upload fails → server returns validation error on that field; all other form state preserved.
- Connection drops mid-fill → auto-save minimizes loss to ~30 seconds. Online-only v1 means no local-first draft store; the `draft` row on the server persists.
- Double-submit → submit endpoint is idempotent: if visit is already in a terminal state, returns the existing outcome.

**Data-integrity:**
- **Vendor edits a critical profile field (business name, address, phone, Ghana Card) after being badged → badge is auto-invalidated.** Observer on `User` / `VendorApplication` clears the cached columns. This is a critical anti-fraud safety net — without it, a scammer could pass verification and then swap their address.
- Two agents start drafts on the same vendor → allowed. Whichever submits first updates the badge; later submission still records a valid visit; observer only extends `field_verified_until` if the new expiry is later than the current one.
- Spoofed GPS → not actively detected in v1. Coordinates are stored so admin can spot-check by comparing to the vendor's address on file. Called out as a known limitation.
- Admin badge revocation → cached columns on user cleared; the `vendor_visits` row is untouched (historical immutability); revocation fields on the visit provide the audit trail.

## 14. Testing Strategy

Follow the project's PHPUnit + Laravel conventions. Every piece of behavior has a test.

### 14.1 Unit — `CompleteVendorVisit` action

- All critical pass + photos + GPS → `passed`, badge issued with +12-month expiry.
- Any single critical fail → `failed`.
- Missing storefront photo → `failed`.
- Missing owner photo → `failed`.
- Missing GPS → `failed`.
- `escalated = true` → `submitted`, regardless of other answers.
- Informational-item fail alone (phone / MoMo) → still `passed`.

### 14.2 Feature — `FieldAgentVisitsController`

- Field agent can start a visit. Items pre-seeded correctly for registered vs. unregistered vendors.
- Field agent cannot start a visit without GPS.
- Field agent cannot open another agent's draft (authorization).
- Field agent cannot start a visit on a non-approved vendor.
- Submit is idempotent (second call returns existing outcome, no duplicate).
- Auto-save PATCH on an item updates only that item.

### 14.3 Feature — admin actions

- Admin can override `passed` → `failed` with a reason; cached columns cleared.
- Admin can override `failed` → `passed` with a reason; cached columns populated.
- Admin can revoke an active badge; cached columns cleared, visit row untouched.
- Override without a reason is rejected (validation).

### 14.4 Feature — observer / cache sync

- Passing visit updates `users.field_verified_at/until`.
- Vendor edits business address → cached columns cleared.
- Vendor with `field_verified_until` in the past is filtered out by "is verified" queries without any job running.

### 14.5 Feature — mobile API

- `GET /api/v1/vendor/field-verification` returns correct payload for the authenticated vendor.
- Customer-facing vendor/product endpoints include `is_field_verified` correctly.
- A vendor cannot read another vendor's visit history.

### 14.6 Authorization

- Non-field-agent user hitting `/field-agent/visits/*` → 403.
- Non-admin user hitting `/admin/vendor-visits/*` → 403.

### 14.7 Tooling

- `php artisan test --compact --filter=VendorVisit` during iteration.
- Full suite before commit.
- `vendor/bin/pint --dirty --format agent` before finalizing.

## 15. Out of Scope for v1 (deferred)

- Offline / PWA / local-first drafts.
- Admin-editable checklist templates. Items stay hardcoded; promote if pain shows.
- Regional scoping of which vendors an agent sees.
- Bulk admin actions.
- GPS anti-spoofing detection.
- Automated agent-performance dashboards / analytics.
- Field-agent-initiated new-vendor sign-ups (separate sub-spec).
- Customer-complaint-driven re-visit triggering. (Admin can already flag vendors manually.)
- Paying agents per visit / commission integration. (Visits may later integrate with Earnings; not in v1.)
