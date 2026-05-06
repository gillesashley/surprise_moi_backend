# Field Agent Teams — Design

**Date:** 2026-05-05
**Status:** Approved (pending implementation plan)
**Scope:** Backend (`surprise_moi_backend`) — Laravel 12 + Inertia v2 + React 19

## 1. Problem & Goal

A field agent currently registered as a "team" lead (`users.is_team_field_agent = true`) qualifies for a higher referral commission percentage (35%, settings key `referral_bonus_field_agent_team_pct`). The "team" exists in name only — leads cannot actually create accounts for the helpers they work with in the field.

Goal: enable a team-lead field agent to self-service create login accounts for their team members from the existing field-agent dashboard, so members can independently onboard and run questionnaires for vendors. All earnings from member activity continue to credit the lead; the lead pays members offline.

## 2. Non-goals

- No multi-level hierarchy. Members cannot create their own members.
- No commission split or per-member earnings tracking in money. Only the lead earns.
- No admin approval workflow for member account creation. Fully self-service for the lead.
- No member intake via `field_agent_applications`. Members bypass Ghana Card / selfie / region intake entirely; the lead vouches.
- No team-size cap.
- No referral codes for members. Members use the lead's code, resolved server-side.

## 3. Roles & Definitions

| Term | Definition |
|---|---|
| **Lead** | A user with `role='field_agent' AND is_team_field_agent=true AND parent_user_id IS NULL`. Has gone through `field_agent_applications` and been admin-approved. Owns a referral code. Can create members. |
| **Member** | A user with `role='field_agent' AND parent_user_id IS NOT NULL AND is_team_field_agent=false`. Created directly by their lead, no application. Has no referral code of their own. |
| **Solo agent** | A field agent with `is_team_field_agent=false AND parent_user_id IS NULL`. Cannot create members. Existing behavior; out of scope. |

## 4. Data Model

### 4.1 New columns on `users`

| Column | Type | Notes |
|---|---|---|
| `parent_user_id` | `BIGINT UNSIGNED NULL`, FK → `users.id` `ON DELETE RESTRICT`, indexed | NULL for leads/non-members. Non-null for members, pointing at their lead. |
| `is_active` | `BOOLEAN DEFAULT TRUE` | Soft-disable. Deactivated users cannot log in. Applied generically across roles, but only mutated by this feature for now. |
| `must_change_password` | `BOOLEAN DEFAULT FALSE` | Set TRUE when a lead provisions a member; first login forces a password change. |
| `location` | `VARCHAR(255) NULL` | Free-text location entered by the lead at member creation. |

### 4.2 New column on `vendor_applications`

| Column | Type | Notes |
|---|---|---|
| `onboarded_by_user_id` | `BIGINT UNSIGNED NULL`, FK → `users.id` `ON DELETE SET NULL`, indexed | The agent who actually submitted the application. Member's id when a member submits; lead's id when the lead submits. The `referral_code_id` on the same row continues to point to the lead's code in both cases. |

### 4.3 Invariants (enforced in FormRequest / Policy)

- A member's `parent_user_id` must point to a row where `parent_user_id IS NULL AND is_team_field_agent = TRUE` (i.e., a lead).
- `parent_user_id` is **immutable** after creation. No reassignment between leads.
- Members cannot have `is_team_field_agent = TRUE` (no nested teams).
- `email` and `phone` remain globally unique (existing `users` constraints).
- Members are never represented in `field_agent_applications`.

### 4.4 Earnings attribution

When a member submits a vendor application:

1. The application's `referral_code_id` is auto-resolved to the **lead's** active code (`referral_codes.influencer_id = lead.id`), server-side. Member never enters a code.
2. The application's `onboarded_by_user_id` is set to the member's id (the authenticated user).
3. On vendor approval, existing earning-creation logic runs unchanged — it credits `referral_codes.influencer_id`, which is the lead. `earnings.user_id = lead.id` automatically.
4. Per-member breakdown for the lead's dashboard comes from joining `vendor_applications.onboarded_by_user_id` against the lead's members.

No new column on `earnings`.

### 4.5 Migration

A single transactional migration `2026_05_05_xxxxxx_add_team_member_fields_to_users_and_vendor_applications.php` adds all five columns and their indexes/FKs.

## 5. API & Routes

All new routes live inside the existing `Route::middleware(['auth', 'dashboard'])->prefix('field-agent')` group in `routes/web.php`.

### 5.1 Lead-only team management

Gated by `Gate::define('manageTeam', fn(User $u) => $u->is_team_field_agent && $u->parent_user_id === null)`.

| Method | Path | Controller action | Inertia page |
|---|---|---|---|
| GET | `/field-agent/team` | `TeamMemberController@index` | `field-agent/team/index` |
| GET | `/field-agent/team/new` | `TeamMemberController@create` | `field-agent/team/new` |
| POST | `/field-agent/team` | `TeamMemberController@store` | (redirect to index) |
| GET | `/field-agent/team/{member}` | `TeamMemberController@show` | `field-agent/team/show` |
| PATCH | `/field-agent/team/{member}` | `TeamMemberController@update` | (redirect to show) |

`{member}` is route-model-bound to `User`, with `TeamMemberPolicy::view`/`update` requiring `$member->parent_user_id === $user->id`.

### 5.2 `StoreTeamMemberRequest`

- `authorize()`: `auth()->user()->is_team_field_agent && parent_user_id === null`
- Rules:
  - `name`: required, string, max:255
  - `email`: required, email, unique:users
  - `phone`: required, valid Ghana phone format (mirror existing rule used in `field_agent_applications`), unique:users
  - `location`: required, string, max:255
- On store: create user with `role='field_agent'`, `parent_user_id=auth()->id()`, `is_team_field_agent=false`, `is_active=true`, `must_change_password=true`, `password=bcrypt(phone)`. No `field_agent_application` row.

### 5.3 `update` (deactivation toggle)

- Body: `{ is_active: boolean }` — only this field is mutable here.
- Cannot edit name/email/phone/location/password through this endpoint.

### 5.4 Vendor-application submission hook

In the existing `VendorApplicationController` (or equivalent path used by the field-agent dashboard's onboarding flow):

- After validation, set `onboarded_by_user_id = auth()->id()` on the new application.
- If the authenticated user is a member (`parent_user_id IS NOT NULL`), resolve `referral_code_id` from the lead's active code rather than from request input. The frontend never collects a referral code from members. If the lead has multiple active codes, use the oldest (`->where('is_active', true)->oldest('id')->first()`) for determinism.
- If the authenticated user is a lead/solo, behavior is unchanged.

### 5.5 Login changes

In Fortify's login response (or equivalent custom login response):

- After authentication but before issuing the session, check `is_active`. If false, log the user out and return a 403 with flash message "Your account has been deactivated. Contact your team lead."

### 5.6 Inertia shared props

Extend `HandleInertiaRequests::share()` to include on `auth.user`:

- `is_team_field_agent` (already needed for nav)
- `parent_user_id`
- `must_change_password`

### 5.7 Force-password-change middleware

`EnforcePasswordChange` middleware, registered in the `dashboard` middleware stack after `auth`:

- If `auth()->user()->must_change_password === true`, every request whose route is not in the allowlist redirects to `/settings/password` with a non-dismissible banner.
- Allowlist: settings password routes, logout.
- After a successful password change, clear `must_change_password` to false in the same request lifecycle.

## 6. Dashboard UI

Existing pages: `dashboard`, `earnings`, `payouts`, `targets`, `verification`, `visits`, `terms`, `register`, plus `components/ReferralCodeCard`.

### 6.1 Visibility matrix

| Page / element | Lead | Member |
|---|---|---|
| `dashboard` | full KPIs | own counts only (vendors onboarded / verified) — **no money** |
| `visits` | unchanged | filtered to `where vendor_applications.onboarded_by_user_id = auth()->id()` |
| `visits/new` | unchanged | unchanged |
| `earnings` | full | **403** |
| `payouts` | full | **403** |
| `targets` | full | **403** |
| `verification` | full | **403** (members never went through this flow) |
| `terms` | full | **403** |
| `ReferralCodeCard` | shown | **hidden** |
| `team/*` (new) | full | **403** |
| Settings / password change | full | full (forced on first login) |

UI scoping is based on the new Inertia shared props. Server-side gates are authoritative.

### 6.2 New pages (lead-only)

```
resources/js/Pages/field-agent/team/
├── index.tsx       Member list with Active/Inactive status, vendors_onboarded /
│                   vendors_verified counts, "Add Member" CTA.
├── new.tsx         Create form: name, email, phone, location. On success, success
│                   toast notes that the member's default password is their phone
│                   number.
└── show.tsx        Member profile, onboarded vendor list (status only, no money),
                    deactivate / reactivate toggle.
```

### 6.3 Navigation

Sidebar entry "Team" appears for users where `is_team_field_agent && !parent_user_id`. Hidden for everyone else.

## 7. Error Handling

| Scenario | Response |
|---|---|
| `email` or `phone` already in `users` on create | 422 with field-specific message |
| Solo field agent hits `/field-agent/team/*` | 403 (gate denies) |
| Member tries `/field-agent/team/*`, `/earnings`, `/payouts`, `/targets`, `/verification`, `/terms` | 403 |
| Lead tries to view/update another lead's member | 403 (`TeamMemberPolicy`) |
| Deactivated member attempts login | 403 with flash, no session issued |
| Member skips forced password change via direct URL | Redirect to `/settings/password` |
| Member tries to create a sub-member (URL hack) | 403 (gate denies — `parent_user_id IS NOT NULL`) |
| Vendor-application submit when lead has no active referral code | 422 with message "Your referral code is not active. Contact support." |
| `parent_user_id` referential integrity | `ON DELETE RESTRICT` — DB rejects deleting a lead with members; app layer already disallows member deletion (deactivate only) |

## 8. Testing

PHPUnit feature tests under `tests/Feature/FieldAgent/Team/`:

- `CreateTeamMemberTest` — happy path, 403 paths, validation failures (duplicate email, duplicate phone, invalid phone format), member fields set correctly (`parent_user_id`, `is_team_field_agent=false`, `is_active=true`, `must_change_password=true`, password hashed from phone).
- `ListTeamMembersTest` — lead sees own members, not others'; `?include_inactive=true` works.
- `ShowTeamMemberTest` — lead sees own member with vendor list (no money fields), 403 for others' members.
- `ToggleTeamMemberActiveTest` — deactivate flips `is_active`; deactivated member's login returns 403; reactivate restores login; cross-lead deactivation forbidden.
- `ForcePasswordChangeTest` — newly created member has flag set; redirected on every protected request; flag clears after change; phone-as-password works for first login.
- `VendorAttributionTest` — when member submits an application, `onboarded_by_user_id = member.id` and `referral_code_id` = lead's active code; on vendor approval, `earnings.user_id = lead.id`; lead's team/show page displays the vendor under the correct member.
- `MemberDashboardScopingTest` — member 403s on `/earnings`, `/payouts`, `/targets`, `/team`, `/verification`, `/terms`; member's `/dashboard` response contains no money fields.

`User` factory gets two states: `::lead()` and `::teamMember(User $lead)`.

## 9. Implementation Notes

- One migration adds all DB columns transactionally.
- Existing earnings creation logic is **untouched** — attribution flows through the lead's referral code, which already lives on the application.
- Inertia shared props change is the smallest possible (three booleans on `auth.user`).
- Gate-based authorization keeps controllers clean; policies handle per-member ownership checks.
- No changes to the `field_agent_applications` table or its admin review workflow.
- Phone-as-default-password is intentional, paired with `must_change_password` to force immediate rotation.
