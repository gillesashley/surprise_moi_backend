# Field Agent Self-Registration — Design Spec

**Date:** 2026-04-15
**Branch:** `feat/field-agent-self-registration`
**Status:** Approved, pending implementation plan

## 1. Goal

Allow anyone to self-register as a field agent from a public page on the dashboard. Applicants submit identity documents, an admin reviews, and on approval the applicant becomes a logged-in User with role `field_agent` who can access only the field-agent area of the dashboard.

## 2. Non-Goals

- OCR / automated Ghana card verification
- SMS verification of phone number at registration time
- Google reCAPTCHA (honeypot + throttle is enough for v1)
- Image compression / resizing (5 MB upload cap is enough for v1)
- Dusk / end-to-end browser tests

## 3. Scope — Existing vs. New

**Already in the codebase:**

- `role = 'field_agent'` on the `users` table; `User::isFieldAgent()` helper.
- Field-agent dashboard pages: `resources/js/pages/field-agent/{dashboard,targets,earnings,payouts}.tsx`.
- Field-agent web routes at `routes/web.php:226–236` guarded by `EnsureDashboardAccess`.
- Relationships on `User`: `targets()`, `activeTargets()`, `targetAchievements()`, `earnings()`, `payoutRequests()`.
- `KairosAfrikaSmsService`, `SmsChannel`, `SmsMessage`, SMS provider contract — fully wired SMS infrastructure.
- `VendorApplication` flow — the pattern to mirror for admin review, status transitions, approval/rejection emails.

**New in this work:**

- `regions` and `cities` tables (Ghana's 16 regions + curated city list).
- `field_agent_applications` table and model.
- Public registration wizard (Inertia, 4 steps).
- Admin review pages (list + detail) with approve/reject actions.
- `RestrictFieldAgentAccess` middleware to fence field agents into their own area.
- Sidebar entries: admin-side "Field Agent Applications" (with pending-count badge); field-agent-side full nav (Dashboard, Targets, Earnings, Payouts, Verification).
- Notifications: application received, approved, rejected — each dual-channel (mail + SMS), queued.
- Login redirect rule: `field_agent` role lands on `/field-agent/dashboard`.
- A read-only "My Verification" page for approved agents.

**Explicitly untouched:**

- The four existing field-agent pages (dashboard/targets/earnings/payouts) — left as-is unless something genuinely blocks registration/login/approval.
- Fortify's login form. No UX changes; only the post-auth redirect logic and a login-time status check.

## 4. Architecture

### 4.1 Data model

**`regions`**

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| name | string | |
| slug | string, unique | |
| timestamps | | |

**`cities`**

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| region_id | FK → regions, cascadeOnDelete | |
| name | string | |
| slug | string | unique `(region_id, slug)` |
| timestamps | | |

**`field_agent_applications`**

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| first_name | string | |
| last_name | string | |
| email | string, unique against non-rejected rows + `users.email` | |
| contact_number | string | stored in `+233XXXXXXXXX` form |
| region_id | FK → regions | |
| city_id | FK → cities | |
| location | string | free-text (neighborhood, landmark) |
| ghana_card_number | string | regex `^GHA-\d{9}-\d$`; unique against non-rejected rows |
| ghana_card_image_path | string | public disk |
| selfie_path | string | public disk |
| password | string, nullable | hashed; copied to new User on approve, then set to null |
| status | enum | `pending`, `under_review`, `approved`, `rejected` |
| reviewed_by | FK → users, nullable | |
| reviewed_at | timestamp, nullable | |
| rejection_reason | text, nullable | |
| approved_user_id | FK → users, nullable | set on approve |
| timestamps | | |

No changes to the `users` table. Field agents become regular User rows with `role='field_agent'` on approval.

### 4.2 Units

**Registration unit** — public-facing.

- `FieldAgentRegistrationController` (`create`, `store`, `submitted`).
- `StoreFieldAgentApplicationRequest` — server-side validation of the full wizard payload.
- `FieldAgentApplicationService::create(array $data): FieldAgentApplication` — stores files, persists the row, dispatches `FieldAgentApplicationReceivedNotification`.
- `RegionLookupController` — `GET /field-agents/regions` returning regions with their cities.

**Admin review unit** — authenticated admin only.

- `Admin\FieldAgentApplicationController` (`index`, `show`, `approve`, `reject`).
- `FieldAgentApprovalService::approve(FieldAgentApplication, User $admin): User` — creates User with role `field_agent`, copies hashed password across, clears `password` from the application, sets `status/reviewed_by/reviewed_at/approved_user_id`, dispatches `FieldAgentApprovedNotification`.
- `FieldAgentApprovalService::reject(FieldAgentApplication, User $admin, string $reason): void` — sets `status=rejected`, `reviewed_by/at`, `rejection_reason`; dispatches `FieldAgentRejectedNotification`.
- `RejectFieldAgentApplicationRequest` — validates `rejection_reason`.

**Gating unit** — runtime access control.

- `RestrictFieldAgentAccess` middleware registered globally after `auth` in `bootstrap/app.php`. Logic: if `auth()->user()->role !== 'field_agent'`, pass. Else: allow route name prefixes `field-agent.*`, `profile.*`, `password.*`, `logout`. Deny everything else with redirect (not 403) to `field-agent.dashboard`.
- `FortifyServiceProvider` — extend post-login redirect so `role === 'field_agent'` routes to `/field-agent/dashboard`.
- Login-time status check via `Fortify::authenticateUsing()`:
  - If an approved User exists for the email and the password matches: return the User (standard).
  - Else if a `field_agent_applications` row exists for the email and the submitted password matches the stored hash: return a validation error whose message reflects the application status (`pending`/`under_review` → "under review"; `rejected` → "not approved"). Prevents account-enumeration on wrong password because the password must match before the status leaks.
  - Else: standard invalid-credentials failure.

**Notifications unit** — dual-channel, queued.

- `FieldAgentApplicationReceivedNotification` — `via = ['mail', 'sms']`.
- `FieldAgentApprovedNotification` — `via = ['mail', 'sms']`.
- `FieldAgentRejectedNotification` — `via = ['mail', 'sms']`. Includes `rejection_reason`; SMS truncates to single segment.
- `FieldAgentApplication` implements `routeNotificationForSms()` returning `contact_number`.

**Regions unit** — lookup.

- `Region` and `City` Eloquent models.
- `RegionCitySeeder` populates the 16 regions and a curated city list per region.

### 4.3 Happy-path data flow

```
Public /field-agents/register (Inertia wizard)
  Step 1 Personal → Step 2 Location → Step 3 Identity → Step 4 Review
  → POST /field-agents/register  (multipart, throttle 5/hr/IP, honeypot)
    → StoreFieldAgentApplicationRequest (server validation)
    → FieldAgentApplicationService::create
        ├─ Storage::disk('public')->put('field-agents/ghana-cards/…')
        ├─ Storage::disk('public')->put('field-agents/selfies/…')
        ├─ FieldAgentApplication::create(status=pending, password=hashed)
        └─ notify FieldAgentApplicationReceivedNotification
    → redirect /field-agents/register/submitted

Admin /admin/field-agent-applications
  → filterable index (status, region), pending badge in sidebar
  → detail page shows all fields + both images
    → Approve button → FieldAgentApprovalService::approve
        ├─ User::create(role='field_agent', password=copied)
        ├─ application: status=approved, approved_user_id, reviewed_by/at, password=null
        └─ notify FieldAgentApprovedNotification
    OR Reject (with reason) → FieldAgentApprovalService::reject
        ├─ application: status=rejected, reviewed_by/at, rejection_reason
        └─ notify FieldAgentRejectedNotification

Agent /login
  → Fortify authenticateUsing override (see gating unit)
  → on success redirect /field-agent/dashboard
  → RestrictFieldAgentAccess fences them into field-agent.* + profile.*
```

## 5. Validation & Security

- **Email.** `email:rfc,dns`; unique against `users.email` AND non-rejected `field_agent_applications.email`.
- **Contact number.** Ghana format: `^(0\d{9}|\+233\d{9})$`; normalized to `+233XXXXXXXXX` before persist.
- **Ghana card number.** `^GHA-\d{9}-\d$` (trim + uppercase before validation); unique against non-rejected applications.
- **Password.** Laravel default: `min:8`, mixed case, numbers; `confirmed`; `Hash::make` before persist.
- **Uploads.** `image`, `mimes:jpeg,jpg,png,webp`, `max:5120` (5 MB); stored on `public` disk via `store()` (random filename; no user-controlled paths).
- **Rate limiting.** `throttle:5,60` on POST register; `throttle:30,1` on regions lookup.
- **Honeypot.** Hidden `website` field; any non-empty value → silent 302 to submitted page, no row.
- **CSRF.** Standard Inertia web-middleware protection.
- **Authorization.**
  - Registration + regions lookup: public.
  - Admin routes: `auth` + admin-role gate (mirroring `VendorApplicationController`).
  - Field-agent routes: `auth` + `RestrictFieldAgentAccess`.
- **Rejected resubmission.** Rejected rows are retained for audit but ignored by uniqueness checks on email + Ghana card number, so an applicant can re-apply after rejection.
- **Account-enumeration protection at login.** Status is only revealed when the submitted password matches the stored application hash.

## 6. Routes Summary

```
Public
  GET  field-agents.register        → FieldAgentRegistrationController@create
  GET  field-agents.regions         → RegionLookupController@index
  POST field-agents.register.store  → FieldAgentRegistrationController@store   [throttle:5,60]
  GET  field-agents.register.submitted → FieldAgentRegistrationController@submitted

Admin (auth, admin-role)
  GET  admin.field-agent-applications.index    → Admin\FieldAgentApplicationController@index
  GET  admin.field-agent-applications.show     → Admin\FieldAgentApplicationController@show
  POST admin.field-agent-applications.approve  → @approve
  POST admin.field-agent-applications.reject   → @reject

Field agent (auth, RestrictFieldAgentAccess)
  [existing] field-agent.dashboard, field-agent.targets, field-agent.earnings, field-agent.payouts
  GET  field-agent.verification   → FieldAgentVerificationController@show   (new, read-only)
```

## 7. Frontend Structure

```
resources/js/pages/
  field-agent/
    register/
      index.tsx        # 4-step wizard (public)
      submitted.tsx    # thank-you page
    verification.tsx   # read-only post-approval view
    dashboard.tsx      # existing
    targets.tsx        # existing
    earnings.tsx       # existing
    payouts.tsx        # existing
  admin/
    field-agent-applications/
      index.tsx        # list + filters + pending badge
      show.tsx         # detail + approve/reject
```

**Wizard implementation:** `useForm` from `@inertiajs/react` for step state and submission (`<Form>` component doesn't fit multi-step). Per-step client validation on "Next"; full server revalidation on final submit. File inputs on Identity step; `forceFormData: true` on submit.

**Sidebar** (`resources/js/components/app-sidebar.tsx`):

- Admin sidebar: add "Field Agent Applications" entry with a pending-count badge. Count is surfaced via `HandleInertiaRequests` shared props (mirror whatever existing pending-count pattern the codebase uses — check before inventing a new one).
- Field-agent sidebar: new role-specific section with Dashboard, My Targets, My Earnings, Payouts, My Verification.

## 8. Testing Strategy

PHPUnit (not Pest), feature tests by default, factories for model setup, `Storage::fake('public')` and `Notification::fake()` for isolation.

**Feature tests:**

1. `tests/Feature/FieldAgentRegistrationTest.php`
   - Registration page loads for guests.
   - `GET /field-agents/regions` returns 16 regions with cities.
   - Valid submission creates a `pending` application, stores both files at expected paths, redirects to submitted page, dispatches `FieldAgentApplicationReceivedNotification` on both channels.
   - Password is hashed (not plaintext) on the application row.
   - Honeypot filled → silent redirect, no row persisted.
   - 6th submission from the same IP in an hour → 429.
   - Validation matrix: each required field missing; invalid email; invalid Ghana card format; invalid phone format; non-image file; oversized file (>5 MB); city-region mismatch; duplicate email against existing User; duplicate email against pending application; duplicate Ghana card against non-rejected application.
   - After a rejection, the same email can re-apply successfully.

2. `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`
   - Non-admin → 403.
   - Index lists applications, filterable by `status` and `region_id`.
   - Show page exposes both image URLs.
   - Approve: creates a User with `role='field_agent'` and the carried-over password hash; application updated (`status=approved`, `reviewed_by`, `reviewed_at`, `approved_user_id`, `password=null`); `FieldAgentApprovedNotification` dispatched on both channels.
   - Reject: requires `rejection_reason`; updates status/reviewed_by/at/rejection_reason; `FieldAgentRejectedNotification` dispatched on both channels.
   - Cannot approve or reject an already-reviewed application (returns validation error).

3. `tests/Feature/FieldAgentLoginFlowTest.php`
   - Approved field agent logs in → redirected to `/field-agent/dashboard`.
   - Pending applicant with correct password → "under review" error message.
   - Rejected applicant with correct password → "not approved" error message.
   - Wrong password on a pending/rejected application → generic invalid-credentials (no status leak).

4. `tests/Feature/FieldAgentAccessRestrictionTest.php`
   - Field agent requesting `/users`, `/vendors`, `/admin/*` → redirect to `/field-agent/dashboard`.
   - Field agent requesting `/field-agent/*` and `/profile` → allowed.
   - Customers, vendors, admins unaffected by the middleware.

**Unit tests:**

5. `tests/Unit/FieldAgentApplicationTest.php` — status transition helpers (`markApproved`, `markRejected`, `canBeReviewed`), `routeNotificationForSms` returns the contact number.
6. `tests/Unit/RegionCityTest.php` — `Region::cities()` and `City::region()` relationships.

**Factories:** `RegionFactory`, `CityFactory`, `FieldAgentApplicationFactory` with states `pending()`, `underReview()`, `approved()`, `rejected()`.

**Seeders:** `RegionCitySeeder` with GSS-reference region + city list.

## 9. Risks & Open Questions

- **Admin role check.** The exploration showed `admin` and `super_admin` roles but didn't surface the exact middleware name. During implementation, mirror whatever `VendorApplicationController` uses rather than inventing a new gate.
- **Existing field-agent pages' state.** The spec deliberately leaves the four existing pages untouched. If, during implementation, one of them proves incompatible with a freshly-approved agent who has no data yet (e.g., hard-crashes on empty `targets` collection), we'll treat that as a "genuinely blocks the new flow" case and fix the minimum needed.
- **Pending-count badge pattern.** The sidebar needs to surface a pending-count badge for admins. The implementation plan should check whether a shared-props pattern already exists (e.g., for vendor applications) and mirror it rather than invent.
- **Password re-entry on rejection resubmission.** Current design requires a rejected applicant to re-enter their password on the wizard — we don't preserve anything across attempts. This is intentional (security) and simple.
