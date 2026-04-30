# Team Field Agent Registration — Design

Date: 2026-04-30

## 1. Objective

Allow a field agent registering at `/field-agents/register` to indicate that they will be operating as a **team leader** rather than as an individual. The registration form, the application/user model, the admin review screens, and the commission settings page all gain awareness of this distinction.

The leader is the only person with a User account. Teammates are coordinated by the leader off-platform; the system intentionally does not store teammate identities, credentials, or contact information. The team flag exists so admins can:

- Recognise team-led field agents when assigning targets and reviewing applications.
- Pay team-led field agents at a different commission rate than individuals.

The individual field agent flow is preserved byte-identically for any application or user where the flag is `false`.

## 2. Out of scope

- No team-member user accounts, identity verification, file uploads, or notifications.
- No system-imposed default targets per agent type. Admins continue to assign targets manually.
- No copy changes to existing approval/rejection notifications.
- No badge on vendor-facing or customer-facing screens.
- No retroactive flag flip for existing field agents. Anyone approved before this ships remains `is_team_field_agent = false` and continues to earn at the individual rate.
- No "convert individual to team" admin action. If management later wants this, it is its own change.

## 3. Data model

Two additive boolean columns. Both default `false`, so all existing rows are correct without backfill and all existing code paths continue working unchanged.

### 3.1 `field_agent_applications`

```php
Schema::table('field_agent_applications', function (Blueprint $table) {
    $table->boolean('is_team')->default(false)->after('location');
});
```

`after('location')` keeps "who and where" columns grouped above the identity-document columns. Cosmetic; functionally irrelevant.

### 3.2 `users`

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_team_field_agent')->default(false)->index();
});
```

Indexed because admin views will filter by it (`where role = 'field_agent' and is_team_field_agent = true`) and the column is queried by the commission activation path on every approved vendor referral.

The `users` table is hot — auth touches it on every request. The migration must add the index using `CREATE INDEX CONCURRENTLY` in production (Laravel: `DB::statement('CREATE INDEX CONCURRENTLY ...')` outside of the schema closure, or split the column add and the index add). See §10 for operational notes.

### 3.3 Models

`App\Models\FieldAgentApplication`:
- Add `is_team` to `$fillable`.
- Add `'is_team' => 'boolean'` in `casts()`.
- Add `public function isTeam(): bool { return (bool) $this->is_team; }`.

`App\Models\User`:
- Add `is_team_field_agent` to `$fillable`.
- Add `'is_team_field_agent' => 'boolean'` in `casts()`.
- Add `public function isTeamFieldAgent(): bool { return $this->isFieldAgent() && $this->is_team_field_agent; }`. The `isFieldAgent()` guard prevents nonsensical reads on non-field-agent rows where the column happens to be `false`.

### 3.4 Why two columns and not one

The application is the registration artifact (what they asked for at signup). The user record is the live operating state (what they currently are). Commission activation runs every time a vendor application is approved; it must read the user, not join through to the application. Mirroring the flag on `users` keeps that path single-table. This matches the existing pattern for `vendor_tier`, which is captured on `vendor_applications` and copied onto `users`.

## 4. Registration UI

Same wizard at `/field-agents/register`, same four steps (Personal, Location, Identity, Review), same upload handling. One added field.

### 4.1 Step 1 (Personal) — checkbox

Below the password fields:

- Checkbox label: "I am registering as a team leader."
- Helper text below the checkbox: "Tick this if you'll be coordinating a team of field agents under your account."

State change in `resources/js/pages/field-agent/register/index.tsx`:

- Add `is_team: boolean` (default `false`) to `WizardData`.
- Add `is_team: false` to the `useForm` defaults.
- Bind the checkbox to `data.is_team` and `setData('is_team', ...)`.

### 4.2 Step 4 (Review)

Add one more `<ReviewRow />`:

```tsx
<ReviewRow label="Registration type" value={data.is_team ? 'Team' : 'Individual'} />
```

So the leader sees their selection before submitting.

### 4.3 Validation — `StoreFieldAgentApplicationRequest`

Add one rule:

```php
'is_team' => ['nullable', 'boolean'],
```

Nullable because unchecked HTML checkboxes are absent from the POST body. The controller normalises absent → `false` via `$request->boolean('is_team')` before passing to the service. The `safe()->except([...])` call in the controller does not strip `is_team`, so it flows through with the rest of the validated payload.

### 4.4 Service — `FieldAgentApplicationService::create`

The `$validated` array gains an `is_team` key (boolean). Pass it into the `FieldAgentApplication::create([...])` call:

```php
'is_team' => $validated['is_team'] ?? false,
```

No signature change. Existing callers (only the registration controller) keep working.

## 5. Approval flow

`App\Services\FieldAgentApprovalService::approve()` — one added field on the `User::create([...])` call:

```php
'is_team_field_agent' => $application->is_team,
```

Everything else in approval is unchanged: same referral code generation (one code, prefix `FAG`), same notification, same audit record, same status transition, same `approved_user_id` linkage. The flag flows: application → user, once, at approval time.

After approval, the user's `is_team_field_agent` is the live source of truth. The application's `is_team` becomes a historical artifact.

### 5.1 Edge case: pending applications created before the migration

Any application already in the system has `is_team = false` after the migration runs (default). Approving one of those produces an individual field agent. Zero behaviour change for in-flight applications.

## 6. Commission settings

### 6.1 New setting key

Key: `referral_bonus_field_agent_team_pct`. Type: `number`. Stored in the existing `settings` table.

### 6.2 Backend — `App\Http\Controllers\Settings\VendorOnboardingController::update`

Add to the validation array:

```php
'referral_bonus_field_agent_team_pct' => 'required|numeric|min:0|max:100',
```

The existing `foreach ($validated as $key => $value) { Setting::set(...) }` loop persists the new key automatically.

### 6.3 Frontend — `resources/js/pages/settings/vendor-onboarding.tsx`

Two changes:

1. Add an entry to the `BONUS_CATEGORIES` array:

```ts
{ key: 'referral_bonus_field_agent_team_pct', label: 'Field Agent (Team)', defaultValue: '35.00' },
```

The default `35.00` is a placeholder; product picks the actual launch value.

2. Extend the `Settings` type with the matching optional shape (mirrors the other entries).

The grid in `ReferralBonusFields` already renders via `BONUS_CATEGORIES.map(...)`, so no template surgery is needed.

### 6.4 Seeding

Add a data migration that calls `Setting::set('referral_bonus_field_agent_team_pct', '35.00', 'number')` if the row is missing. This guarantees the settings page renders with a real number on first load rather than blank, and survives later changes to default-handling code.

## 7. Commission activation

`App\Services\ReferralService::activateReferral()` — replace this line:

```php
$percentage = (float) Setting::get("referral_bonus_{$sharer->role}_pct", 0);
```

With:

```php
$settingKey = $sharer->isTeamFieldAgent()
    ? 'referral_bonus_field_agent_team_pct'
    : "referral_bonus_{$sharer->role}_pct";

$percentage = (float) Setting::get($settingKey, 0);
```

Three lines. Every other role (`influencer`, `employee`, `customer`, `vendor`, individual `field_agent`) takes the existing branch with no behaviour change.

The branch is intentionally hardcoded to `field_agent_team`. There is no team variant for any other role in this design. Generalising for one case would be speculative flexibility; if `influencer_team` ever appears, refactor then.

## 8. Admin views

### 8.1 `resources/js/pages/admin/field-agent-applications/index.tsx`

- Render a small "Team" badge next to the applicant's name when `application.is_team` is `true`. Neutral colour (e.g. the `secondary` chip variant already used elsewhere). No "Individual" badge for the false case — less visual noise.
- Add a filter chip set above the list: All / Individual / Team. Wired as a query string parameter `?type=team|individual`.

### 8.2 `resources/js/pages/admin/field-agent-applications/show.tsx`

In the application summary card, add a row:

```
Registration type: Team
```

(or Individual). Bold so it does not disappear next to other rows. This is the screen where the admin makes the approve/reject decision; the flag has to be unmistakable here.

### 8.3 `resources/js/pages/users/index.tsx`

When a row's role is `field_agent` and `is_team_field_agent` is `true`, append a "Team" badge next to the role pill.

### 8.4 Backend changes

- `App\Http\Controllers\Admin\FieldAgentApplicationController::index` — accept an optional `type` filter alongside the existing `status` and `region_id` filters. Validation: `Rule::in(['individual', 'team'])`. Apply: `where('is_team', $type === 'team')` when provided.
- `App\Http\Controllers\Admin\FieldAgentApplicationController::show` — `$fieldAgentApplication->toArray()` already serialises all model attributes, so `is_team` rides through with no extra wiring.
- `App\Http\Controllers\UserController` (the index powering `resources/js/pages/users/index.tsx`) — confirm `is_team_field_agent` is in the response payload. If the response uses `select(...)` or a Resource, add this column.

### 8.5 Screens deliberately untouched

- Vendor visit screens. Vendor admins validating a vendor application do not need to know whether the referrer was a team or individual.
- Field agent's own dashboard. The leader does not need to be told they are a team.
- Customer-facing screens. Vendors and customers never see this distinction.
- Notifications and emails. Existing copy is flag-agnostic.

## 9. Targets

No code change. Targets are already assigned per user by admins. The team badge in the users list and field-agent-applications views gives admins the visual cue to assign a team-appropriate target.

## 10. Operational notes

- The `users.is_team_field_agent` index addition must use `CREATE INDEX CONCURRENTLY` in production to avoid blocking writes on the hottest table in the schema. Implementation: split into two migration steps inside the same migration file. Step 1 adds the column with `->default(false)` (no `->index()`). Step 2 calls `DB::statement('CREATE INDEX CONCURRENTLY users_is_team_field_agent_index ON users (is_team_field_agent)')` outside the schema closure. The migration's `down()` method drops the index first, then the column.
- Seed `referral_bonus_field_agent_team_pct` via the data migration so the settings page is never blank.
- The data migration order is: schema columns first, then setting seed. The setting seed has no schema dependency, but keeping the order canonical avoids surprises.

## 11. Testing

PHPUnit feature tests, matching the existing `tests/Feature/FieldAgent*` style.

### 11.1 `tests/Feature/FieldAgentRegistrationTest.php` (extend)

- Submitting the form with `is_team = true` persists `is_team = true` on the resulting `FieldAgentApplication`.
- Submitting with `is_team` absent (unchecked) persists `is_team = false`.
- Validation rejects non-boolean `is_team` values (e.g. `"banana"`).

### 11.2 `tests/Feature/Admin/FieldAgentApplicationAdminTest.php` (extend)

- Approving an application with `is_team = true` produces a `User` with `is_team_field_agent = true`.
- Approving an application with `is_team = false` produces a `User` with `is_team_field_agent = false` (regression guard for the individual flow).
- The admin index applies `?type=team` and `?type=individual` filters correctly.

### 11.3 `tests/Feature/ReferralBonusTeamFieldAgentTest.php` (new)

- Activating a referral whose sharer is an individual field agent uses `referral_bonus_field_agent_pct` and produces the expected GHS / points amounts.
- Activating a referral whose sharer is a team field agent uses `referral_bonus_field_agent_team_pct`.
- When `referral_bonus_field_agent_team_pct` is missing entirely, activation falls back to `0` (pinning the existing `Setting::get(..., 0)` contract — guards against a future change silently re-routing to the individual rate).

### 11.4 `tests/Feature/Settings/VendorOnboardingSettingsTest.php` (extend)

- `referral_bonus_field_agent_team_pct` is required.
- Out-of-range values (negative, > 100, non-numeric) fail validation.
- A valid value saves and round-trips through `Setting::get`.

### 11.5 Factory

`Database\Factories\FieldAgentApplicationFactory`: add a `team()` state so future tests opt into team applications cleanly:

```php
public function team(): static
{
    return $this->state(fn () => ['is_team' => true]);
}
```

### 11.6 Tests that must remain green without edits

Every existing test that builds a `FieldAgentApplication` via `FieldAgentApplicationFactory` without `team()` defaults to `is_team = false` and behaves identically. The full referral flow tests (sharer is a non-team field agent) continue to read `referral_bonus_field_agent_pct`. This is the regression contract.

## 12. Implementation order

1. Migrations: `field_agent_applications.is_team`, then `users.is_team_field_agent` (with `CREATE INDEX CONCURRENTLY`).
2. Data migration: seed `referral_bonus_field_agent_team_pct`.
3. Model edits: `FieldAgentApplication` and `User` (`$fillable`, `casts`, helper).
4. Validation: `StoreFieldAgentApplicationRequest`.
5. Service edits: `FieldAgentApplicationService`, `FieldAgentApprovalService`.
6. Commission branch: `ReferralService::activateReferral`.
7. Settings: controller validation + frontend `BONUS_CATEGORIES` entry.
8. Registration UI: checkbox in Step 1, review row in Step 4.
9. Admin UI: badges in list/show, filter chip in list, users index badge.
10. Tests for each layer as it lands; full suite at the end.
