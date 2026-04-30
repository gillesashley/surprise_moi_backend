# Team Field Agent Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "registering as a team leader" flag to the existing field agent registration flow so admins can identify team-led field agents in their views, set a different commission rate for them, and assign different targets — without disturbing the individual flow.

**Architecture:** Two additive boolean columns. `field_agent_applications.is_team` captures the registration choice; `users.is_team_field_agent` mirrors it on the User after approval, where commission activation reads it. One new setting key `referral_bonus_field_agent_team_pct` plus a single branch in `ReferralService::activateReferral`. UI surfaces: one checkbox on the registration form, one badge in three admin screens, one row in the settings page.

**Tech Stack:** Laravel 12 + Postgres + Inertia/React + PHPUnit. Tests use Postgres (`pgsql`) — `CREATE INDEX CONCURRENTLY` works in the test database.

**Spec:** `docs/superpowers/specs/2026-04-30-team-field-agent-registration-design.md`

**Branch:** `feat/team-field-agent-registration-spec` (already created when the spec was written; implementation continues on this branch).

---

## File Structure

**Create (5 files):**
- `database/migrations/2026_04_30_120000_add_is_team_to_field_agent_applications_table.php`
- `database/migrations/2026_04_30_120001_add_is_team_field_agent_to_users_table.php`
- `database/migrations/2026_04_30_120002_seed_referral_bonus_field_agent_team_pct_setting.php`
- `tests/Feature/ReferralBonusTeamFieldAgentTest.php`

**Modify (13 files):**
- `database/factories/FieldAgentApplicationFactory.php` — add `team()` state
- `app/Models/FieldAgentApplication.php` — `$fillable`, `casts`, `isTeam()`
- `app/Models/User.php` — `$fillable`, `casts`, `isTeamFieldAgent()`
- `app/Http/Requests/StoreFieldAgentApplicationRequest.php` — `is_team` rule
- `app/Http/Controllers/FieldAgentRegistrationController.php` — normalise `is_team` in `store()`
- `app/Services/FieldAgentApplicationService.php` — persist `is_team`
- `app/Services/FieldAgentApprovalService.php` — copy flag onto user
- `app/Services/ReferralService.php` — branch on team status when picking percentage key
- `app/Http/Controllers/Settings/VendorOnboardingController.php` — validation rule
- `app/Http/Controllers/Admin/FieldAgentApplicationController.php` — `type` filter on `index`
- `app/Http/Controllers/UserController.php` — include `is_team_field_agent` in field-agent payload
- `resources/js/pages/field-agent/register/index.tsx` — checkbox + review row
- `resources/js/pages/settings/vendor-onboarding.tsx` — `BONUS_CATEGORIES` entry + type
- `resources/js/pages/admin/field-agent-applications/index.tsx` — Team badge + type filter
- `resources/js/pages/admin/field-agent-applications/show.tsx` — registration-type row
- `resources/js/pages/users/index.tsx` — Team badge next to role pill
- `tests/Feature/FieldAgentRegistrationTest.php` — extend
- `tests/Feature/Admin/FieldAgentApplicationAdminTest.php` — extend
- `tests/Feature/Settings/VendorOnboardingSettingsTest.php` — extend

---

## Task 1: Factory `team()` state

**Why first:** every test in later tasks builds team applications via this state. If we don't add it first, every test would inline `['is_team' => true]`.

**Files:**
- Modify: `database/factories/FieldAgentApplicationFactory.php`

- [ ] **Step 1: Add the `team()` state**

In `database/factories/FieldAgentApplicationFactory.php`, after the existing `rejected()` method, add:

```php
public function team(): static
{
    return $this->state(['is_team' => true]);
}
```

- [ ] **Step 2: Commit**

```bash
git add database/factories/FieldAgentApplicationFactory.php
git commit -m "test(field-agent): add team() factory state for application factory"
```

Note: this commit will not change any test behaviour yet because no migration has added the column. The state is staged ready for use; the column arrives in Task 2.

---

## Task 2: Migration — `field_agent_applications.is_team`

**Files:**
- Create: `database/migrations/2026_04_30_120000_add_is_team_to_field_agent_applications_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_agent_applications', function (Blueprint $table) {
            $table->boolean('is_team')->default(false)->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('field_agent_applications', function (Blueprint $table) {
            $table->dropColumn('is_team');
        });
    }
};
```

Save as `database/migrations/2026_04_30_120000_add_is_team_to_field_agent_applications_table.php`.

- [ ] **Step 2: Run the migration**

```bash
docker compose exec laravel.test php artisan migrate
```

Expected: `Running: ...add_is_team_to_field_agent_applications_table ... DONE`.

- [ ] **Step 3: Run the existing field agent registration tests to confirm zero behaviour change**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentRegistrationTest
```

Expected: all existing tests pass. The default `false` means existing behaviour is unchanged.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_30_120000_add_is_team_to_field_agent_applications_table.php
git commit -m "feat(field-agent): add is_team column to field_agent_applications"
```

---

## Task 3: Migration — `users.is_team_field_agent` with `CREATE INDEX CONCURRENTLY`

**Why split into two operations:** `CREATE INDEX CONCURRENTLY` on Postgres cannot run inside a transaction. Laravel wraps migrations in a transaction by default. We disable that for this migration and run two operations: column add, then concurrent index. This keeps writes on the hot `users` table from blocking when the migration ships to production.

**Files:**
- Create: `database/migrations/2026_04_30_120001_add_is_team_field_agent_to_users_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction on Postgres.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_team_field_agent')->default(false);
        });

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS users_is_team_field_agent_index ON users (is_team_field_agent)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_is_team_field_agent_index');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_team_field_agent');
        });
    }
};
```

Save as `database/migrations/2026_04_30_120001_add_is_team_field_agent_to_users_table.php`.

- [ ] **Step 2: Run the migration**

```bash
docker compose exec laravel.test php artisan migrate
```

Expected output includes both the column add and the concurrent index creation succeed.

- [ ] **Step 3: Verify the index exists**

```bash
docker compose exec laravel.test php artisan tinker --execute="echo \DB::select(\"SELECT indexname FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_is_team_field_agent_index'\")[0]->indexname ?? 'MISSING';"
```

Expected: `users_is_team_field_agent_index`.

- [ ] **Step 4: Run the full test suite to confirm zero regression**

```bash
docker compose exec laravel.test php artisan test --compact
```

Expected: all tests pass. The new column defaults to `false` for every existing user.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_30_120001_add_is_team_field_agent_to_users_table.php
git commit -m "feat(users): add is_team_field_agent column with concurrent index"
```

---

## Task 4: Setting seed migration

**Files:**
- Create: `database/migrations/2026_04_30_120002_seed_referral_bonus_field_agent_team_pct_setting.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('settings')
            ->where('key', 'referral_bonus_field_agent_team_pct')
            ->exists();

        if ($existing) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'referral_bonus_field_agent_team_pct',
            'value' => '35.00',
            'type' => 'number',
            'description' => 'Field Agent (Team) referral bonus percentage (applied to referred vendor\'s tier onboarding fee)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'referral_bonus_field_agent_team_pct')
            ->delete();
    }
};
```

Save as `database/migrations/2026_04_30_120002_seed_referral_bonus_field_agent_team_pct_setting.php`.

- [ ] **Step 2: Run the migration**

```bash
docker compose exec laravel.test php artisan migrate
```

- [ ] **Step 3: Verify the setting was seeded**

```bash
docker compose exec laravel.test php artisan tinker --execute="echo \App\Models\Setting::get('referral_bonus_field_agent_team_pct');"
```

Expected output: `35` (cast to number) or `35.00` depending on Setting's number cast.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_30_120002_seed_referral_bonus_field_agent_team_pct_setting.php
git commit -m "feat(settings): seed referral_bonus_field_agent_team_pct default"
```

---

## Task 5: `FieldAgentApplication` model

**Files:**
- Modify: `app/Models/FieldAgentApplication.php`

- [ ] **Step 1: Write a failing model test**

Create `tests/Unit/FieldAgentApplicationTeamFlagTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\FieldAgentApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentApplicationTeamFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_team_defaults_to_false(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertFalse($app->is_team);
        $this->assertFalse($app->isTeam());
    }

    public function test_is_team_can_be_set_via_factory_state(): void
    {
        $app = FieldAgentApplication::factory()->team()->create();

        $this->assertTrue($app->is_team);
        $this->assertTrue($app->isTeam());
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationTeamFlagTest
```

Expected: test fails because `isTeam()` does not exist on the model and/or because `is_team` is not in `$fillable` so the factory state cannot persist it.

- [ ] **Step 3: Update the model**

In `app/Models/FieldAgentApplication.php`:

Add `'is_team'` to `$fillable` (place it after `'location'`):

```php
protected $fillable = [
    'first_name',
    'last_name',
    'email',
    'contact_number',
    'region_id',
    'city_id',
    'location',
    'is_team',
    'ghana_card_number',
    'ghana_card_image_path',
    'ghana_card_back_image_path',
    'selfie_path',
    'password',
    'status',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
    'approved_user_id',
];
```

Add `'is_team' => 'boolean'` to the `casts()` method:

```php
protected function casts(): array
{
    return [
        'reviewed_at' => 'datetime',
        'status' => FieldAgentApplicationStatus::class,
        'is_team' => 'boolean',
    ];
}
```

Add the `isTeam()` helper alongside the existing `fullName()` method:

```php
public function isTeam(): bool
{
    return (bool) $this->is_team;
}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationTeamFlagTest
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/FieldAgentApplication.php tests/Unit/FieldAgentApplicationTeamFlagTest.php
git commit -m "feat(field-agent): expose is_team on FieldAgentApplication model"
```

---

## Task 6: `User` model

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write a failing model test**

Create `tests/Unit/UserTeamFieldAgentTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTeamFieldAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_false(): void
    {
        $user = User::factory()->create(['role' => 'field_agent']);

        $this->assertFalse($user->is_team_field_agent);
        $this->assertFalse($user->isTeamFieldAgent());
    }

    public function test_team_field_agent_returns_true_when_role_and_flag_match(): void
    {
        $user = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);

        $this->assertTrue($user->isTeamFieldAgent());
    }

    public function test_non_field_agent_role_short_circuits_to_false(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_team_field_agent' => true,
        ]);

        $this->assertFalse(
            $user->isTeamFieldAgent(),
            'Even with the flag set, a non-field-agent must not report as team'
        );
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
docker compose exec laravel.test php artisan test --compact --filter=UserTeamFieldAgentTest
```

Expected: tests fail — `isTeamFieldAgent()` does not exist; the column is not fillable so the factory cannot set it.

- [ ] **Step 3: Update the User model**

In `app/Models/User.php`:

Add `'is_team_field_agent'` to `$fillable` (append to the existing list):

```php
protected $fillable = [
    'name',
    'email',
    'phone',
    'password',
    'avatar',
    'banner',
    'provider',
    'provider_id',
    'role',
    'vendor_tier',
    'vendor_hash',
    'business_name',
    'date_of_birth',
    'gender',
    'bio',
    'favorite_color',
    'favorite_music_genre',
    'is_popular',
    'field_verified_at',
    'field_verified_until',
    'is_team_field_agent',
];
```

Add the cast in `casts()` (append):

```php
'is_team_field_agent' => 'boolean',
```

Add the helper method near the existing `isFieldAgent()`:

```php
/**
 * Whether this user is a field agent operating as a team leader.
 *
 * The role guard prevents nonsensical reads on rows where the
 * column happens to be false but the user is not a field agent.
 */
public function isTeamFieldAgent(): bool
{
    return $this->isFieldAgent() && (bool) $this->is_team_field_agent;
}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
docker compose exec laravel.test php artisan test --compact --filter=UserTeamFieldAgentTest
```

Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Unit/UserTeamFieldAgentTest.php
git commit -m "feat(user): expose is_team_field_agent on User model"
```

---

## Task 7: Validation rule on `StoreFieldAgentApplicationRequest`

**Files:**
- Modify: `app/Http/Requests/StoreFieldAgentApplicationRequest.php`
- Modify: `tests/Feature/FieldAgentRegistrationTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/FieldAgentRegistrationTest.php` (just before the closing brace of the class, after `test_duplicate_ghana_card_against_non_rejected_blocked`):

```php
public function test_is_team_defaults_to_false_when_omitted(): void
{
    $this->post('/field-agents/register', $this->validPayload());

    $app = FieldAgentApplication::firstOrFail();
    $this->assertFalse($app->is_team);
}

public function test_is_team_persists_when_true(): void
{
    $this->post('/field-agents/register', $this->validPayload(['is_team' => '1']));

    $app = FieldAgentApplication::firstOrFail();
    $this->assertTrue($app->is_team);
}

public function test_is_team_rejects_non_boolean_values(): void
{
    $this->post('/field-agents/register', $this->validPayload(['is_team' => 'banana']))
        ->assertSessionHasErrors('is_team');
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentRegistrationTest
```

Expected: the three new tests fail. Existing tests still pass.

- [ ] **Step 3: Add the validation rule**

In `app/Http/Requests/StoreFieldAgentApplicationRequest.php`, add to the `rules()` array (place anywhere among the existing entries; conventionally near `location`):

```php
'is_team' => ['nullable', 'boolean'],
```

So the array becomes (showing context around the addition):

```php
'location' => ['required', 'string', 'max:160'],
'is_team' => ['nullable', 'boolean'],
'ghana_card_number' => [...],
```

- [ ] **Step 4: Run the failing tests again to confirm they still fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentRegistrationTest
```

The two persistence tests still fail because the controller and service do not yet pass the flag through to the model. Only `test_is_team_rejects_non_boolean_values` should pass after this step.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreFieldAgentApplicationRequest.php tests/Feature/FieldAgentRegistrationTest.php
git commit -m "feat(field-agent): validate is_team on registration request"
```

---

## Task 8: Plumb `is_team` through the registration controller and service

**Files:**
- Modify: `app/Http/Controllers/FieldAgentRegistrationController.php`
- Modify: `app/Services/FieldAgentApplicationService.php`

- [ ] **Step 1: Update `FieldAgentRegistrationController::store`**

In `app/Http/Controllers/FieldAgentRegistrationController.php`, the existing `store` method strips file/honeypot/confirmation keys via `safe()->except(...)`. We must coerce the boolean explicitly so absent / unchecked checkboxes pass through as `false` rather than `null`.

Replace the existing `store` method body with:

```php
public function store(StoreFieldAgentApplicationRequest $request): RedirectResponse
{
    $validated = $request->safe()->except([
        'ghana_card_image',
        'ghana_card_back_image',
        'selfie',
        'website',
        'password_confirmation',
    ]);

    $validated['is_team'] = $request->boolean('is_team');

    $this->service->create(
        $validated,
        [
            'ghana_card_image' => $request->file('ghana_card_image'),
            'ghana_card_back_image' => $request->file('ghana_card_back_image'),
            'selfie' => $request->file('selfie'),
        ]
    );

    return redirect()->route('field-agents.register.submitted');
}
```

- [ ] **Step 2: Update `FieldAgentApplicationService::create`**

In `app/Services/FieldAgentApplicationService.php`, in the `FieldAgentApplication::create([...])` array, add the line `'is_team' => $validated['is_team'] ?? false,` (place it after `'location'`):

```php
$application = FieldAgentApplication::create([
    'first_name' => $validated['first_name'],
    'last_name' => $validated['last_name'],
    'email' => strtolower($validated['email']),
    'contact_number' => $validated['contact_number'],
    'region_id' => $validated['region_id'],
    'city_id' => $validated['city_id'],
    'location' => $validated['location'],
    'is_team' => $validated['is_team'] ?? false,
    'ghana_card_number' => $validated['ghana_card_number'],
    'ghana_card_image_path' => $ghanaCardPath,
    'ghana_card_back_image_path' => $ghanaCardBackPath,
    'selfie_path' => $selfiePath,
    'password' => Hash::make($validated['password']),
    'status' => 'pending',
]);
```

- [ ] **Step 3: Run the registration tests to confirm they all pass**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentRegistrationTest
```

Expected: all tests pass, including the three new ones from Task 7.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FieldAgentRegistrationController.php app/Services/FieldAgentApplicationService.php
git commit -m "feat(field-agent): persist is_team flag from registration form"
```

---

## Task 9: Registration UI — checkbox and review row

**Files:**
- Modify: `resources/js/pages/field-agent/register/index.tsx`

- [ ] **Step 1: Locate the imports and add the Checkbox component**

At the top of `resources/js/pages/field-agent/register/index.tsx`, add this import (placement near the other UI imports):

```tsx
import { Checkbox } from '@/components/ui/checkbox';
```

If `@/components/ui/checkbox` does not exist, use a native checkbox instead in step 3 — render `<input type="checkbox" />` with the surrounding label. Verify with:

```bash
ls resources/js/components/ui/checkbox.tsx
```

Expected: file exists. (The codebase ships shadcn primitives; this should be present.)

- [ ] **Step 2: Add `is_team` to the `WizardData` type and `useForm` defaults**

```tsx
type WizardData = {
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    password: string;
    password_confirmation: string;
    region_id: string;
    city_id: string;
    location: string;
    ghana_card_number: string;
    ghana_card_image: File | null;
    ghana_card_back_image: File | null;
    selfie: File | null;
    website: string;
    is_team: boolean;
};
```

In the `useForm<WizardData>({...})` defaults, append `is_team: false,` to the initial values.

- [ ] **Step 3: Render the checkbox in Step 1 (Personal)**

In Step 1's `<Stack spacing={2.25}>` block, after the password grid (the second `<Box ...>` containing the password fields) and before the closing `</Stack>`, insert:

```tsx
<Stack spacing={1}>
    <Stack
        direction="row"
        spacing={1.25}
        sx={{ alignItems: 'flex-start' }}
    >
        <Checkbox
            id="is_team"
            checked={data.is_team}
            onCheckedChange={(checked) =>
                setData('is_team', checked === true)
            }
        />
        <Stack spacing={0.25}>
            <Label htmlFor="is_team">
                I am registering as a team leader
            </Label>
            <Typography
                variant="caption"
                color="text.secondary"
            >
                Tick this if you'll be coordinating a team of field agents under your account.
            </Typography>
        </Stack>
    </Stack>
    <InputError message={errors.is_team} />
</Stack>
```

- [ ] **Step 4: Add the review row in Step 4**

Inside Step 4's review grid (the `<Box>` containing the `<ReviewRow ... />` calls), add at the end of the grid:

```tsx
<ReviewRow
    label="Registration type"
    value={data.is_team ? 'Team' : 'Individual'}
/>
```

- [ ] **Step 5: Build the frontend**

```bash
docker compose exec laravel.test pnpm run build
```

Expected: clean build, no TS errors.

- [ ] **Step 6: Smoke-test in the browser**

Open `http://localhost:8082/field-agents/register` (or whatever port maps to the dev container).

Walk the wizard:
1. Fill Step 1 fields, tick the team checkbox, click Next.
2. Fill Step 2, click Next.
3. Fill Step 3, click Next.
4. Confirm Step 4 shows "Registration type: Team".
5. Submit.

Verify a row landed in `field_agent_applications` with `is_team = true`:

```bash
docker compose exec laravel.test php artisan tinker --execute="echo \App\Models\FieldAgentApplication::latest()->first()->is_team ? 'TEAM' : 'INDIVIDUAL';"
```

Expected: `TEAM`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/field-agent/register/index.tsx
git commit -m "feat(field-agent): add team-leader checkbox to registration wizard"
```

---

## Task 10: Approval service propagates the flag onto the user

**Files:**
- Modify: `app/Services/FieldAgentApprovalService.php`
- Modify: `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/FieldAgentApplicationAdminTest.php` before the closing brace:

```php
public function test_approval_propagates_is_team_true_onto_user(): void
{
    $app = FieldAgentApplication::factory()->team()->pending()->create([
        'email' => 'teamlead@example.com',
        'password' => Hash::make('AgentSecret1'),
    ]);

    app(\App\Services\FieldAgentApprovalService::class)->approve($app->fresh(), $this->admin);

    $user = User::where('email', 'teamlead@example.com')->firstOrFail();
    $this->assertTrue($user->is_team_field_agent);
    $this->assertTrue($user->isTeamFieldAgent());
}

public function test_approval_propagates_is_team_false_for_individual_applications(): void
{
    $app = FieldAgentApplication::factory()->pending()->create([
        'email' => 'soloagent@example.com',
        'password' => Hash::make('AgentSecret1'),
    ]);

    app(\App\Services\FieldAgentApprovalService::class)->approve($app->fresh(), $this->admin);

    $user = User::where('email', 'soloagent@example.com')->firstOrFail();
    $this->assertFalse($user->is_team_field_agent);
    $this->assertFalse($user->isTeamFieldAgent());
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationAdminTest
```

Expected: the new "is_team_true" test fails because the user is created without that field. The "is_team_false" test passes (default behaviour).

- [ ] **Step 3: Update `FieldAgentApprovalService::approve`**

In `app/Services/FieldAgentApprovalService.php`, add `'is_team_field_agent' => $application->is_team,` to the `User::create([...])` call. The block becomes:

```php
$user = User::create([
    'name' => $application->fullName(),
    'email' => $application->email,
    'password' => $application->password,
    'role' => 'field_agent',
    'phone' => $application->contact_number,
    'email_verified_at' => now(),
    'is_team_field_agent' => $application->is_team,
]);
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationAdminTest
```

Expected: all tests pass, including both new ones.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FieldAgentApprovalService.php tests/Feature/Admin/FieldAgentApplicationAdminTest.php
git commit -m "feat(field-agent): copy is_team to user on approval"
```

---

## Task 11: Settings — controller validation + frontend `BONUS_CATEGORIES`

**Files:**
- Modify: `app/Http/Controllers/Settings/VendorOnboardingController.php`
- Modify: `resources/js/pages/settings/vendor-onboarding.tsx`
- Modify: `tests/Feature/Settings/VendorOnboardingSettingsTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Settings/VendorOnboardingSettingsTest.php` before the closing brace:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function team_field_agent_pct_is_required_on_update(): void
{
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '200',
            'vendor_tier2_onboarding_fee' => '100',
            'vendor_tier1_commission_rate' => '12',
            'vendor_tier2_commission_rate' => '8',
            'referral_bonus_customer_pct' => '15',
            'referral_bonus_vendor_pct' => '20',
            'referral_bonus_influencer_pct' => '25',
            'referral_bonus_field_agent_pct' => '30',
            'referral_bonus_employee_pct' => '20',
            'vendor_onboarding_subsidy_pct' => '25',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
            // intentionally omit referral_bonus_field_agent_team_pct
        ])
        ->assertSessionHasErrors('referral_bonus_field_agent_team_pct');
}

#[\PHPUnit\Framework\Attributes\Test]
public function team_field_agent_pct_must_be_between_0_and_100(): void
{
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '200',
            'vendor_tier2_onboarding_fee' => '100',
            'vendor_tier1_commission_rate' => '12',
            'vendor_tier2_commission_rate' => '8',
            'referral_bonus_customer_pct' => '15',
            'referral_bonus_vendor_pct' => '20',
            'referral_bonus_influencer_pct' => '25',
            'referral_bonus_field_agent_pct' => '30',
            'referral_bonus_employee_pct' => '20',
            'vendor_onboarding_subsidy_pct' => '25',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
            'referral_bonus_field_agent_team_pct' => '150',
        ])
        ->assertSessionHasErrors('referral_bonus_field_agent_team_pct');
}

#[\PHPUnit\Framework\Attributes\Test]
public function team_field_agent_pct_round_trips_through_setting_get(): void
{
    \Illuminate\Support\Facades\Cache::flush();
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '200',
            'vendor_tier2_onboarding_fee' => '100',
            'vendor_tier1_commission_rate' => '12',
            'vendor_tier2_commission_rate' => '8',
            'referral_bonus_customer_pct' => '15',
            'referral_bonus_vendor_pct' => '20',
            'referral_bonus_influencer_pct' => '25',
            'referral_bonus_field_agent_pct' => '30',
            'referral_bonus_employee_pct' => '20',
            'vendor_onboarding_subsidy_pct' => '25',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
            'referral_bonus_field_agent_team_pct' => '42',
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Cache::flush();
    $this->assertEquals(42.0, (float) \App\Models\Setting::get('referral_bonus_field_agent_team_pct'));
}

#[\PHPUnit\Framework\Attributes\Test]
public function migration_seeds_the_team_field_agent_pct_setting(): void
{
    \Illuminate\Support\Facades\Cache::flush();

    $this->assertEquals(35.0, (float) \App\Models\Setting::get('referral_bonus_field_agent_team_pct'));
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=VendorOnboardingSettingsTest
```

Expected: the seed-test passes (Task 4 already inserted the row), but the validation tests fail — the controller does not yet validate the new key.

- [ ] **Step 3: Add the validation rule**

In `app/Http/Controllers/Settings/VendorOnboardingController.php`, add to the `$validated = $request->validate([...])` array (place near the other `referral_bonus_*_pct` rules):

```php
'referral_bonus_field_agent_team_pct' => 'required|numeric|min:0|max:100',
```

- [ ] **Step 4: Run the tests to confirm validation tests pass**

```bash
docker compose exec laravel.test php artisan test --compact --filter=VendorOnboardingSettingsTest
```

Expected: all tests pass.

- [ ] **Step 5: Update the settings frontend `BONUS_CATEGORIES`**

In `resources/js/pages/settings/vendor-onboarding.tsx`:

a) Extend the `Settings` interface (append):

```ts
referral_bonus_field_agent_team_pct?: {
    value: string;
    type: string;
    description: string;
};
```

b) Add an entry to `BONUS_CATEGORIES`:

```ts
const BONUS_CATEGORIES = [
    {
        key: 'referral_bonus_customer_pct',
        label: 'Customer',
        defaultValue: '15.00',
    },
    {
        key: 'referral_bonus_vendor_pct',
        label: 'Vendor',
        defaultValue: '20.00',
    },
    {
        key: 'referral_bonus_influencer_pct',
        label: 'Influencer',
        defaultValue: '25.00',
    },
    {
        key: 'referral_bonus_field_agent_pct',
        label: 'Field Agent',
        defaultValue: '30.00',
    },
    {
        key: 'referral_bonus_field_agent_team_pct',
        label: 'Field Agent (Team)',
        defaultValue: '35.00',
    },
    {
        key: 'referral_bonus_employee_pct',
        label: 'Employee',
        defaultValue: '20.00',
    },
] as const;
```

The `ReferralBonusFields` component iterates this array, so the new card field renders automatically.

- [ ] **Step 6: Build the frontend**

```bash
docker compose exec laravel.test pnpm run build
```

Expected: clean build, no TS errors.

- [ ] **Step 7: Smoke-test the settings page**

Visit `http://localhost:8082/settings/vendor-onboarding` as a super-admin (use credentials from CLAUDE.md). Verify "Field Agent (Team)" appears in the Referral Bonus Percentages card with value `35.00`. Edit it to `40` and save; reload and verify it persisted.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Settings/VendorOnboardingController.php resources/js/pages/settings/vendor-onboarding.tsx tests/Feature/Settings/VendorOnboardingSettingsTest.php
git commit -m "feat(settings): expose referral_bonus_field_agent_team_pct on settings page"
```

---

## Task 12: `ReferralService::activateReferral` branches on team status

**Files:**
- Modify: `app/Services/ReferralService.php`
- Create: `tests/Feature/ReferralBonusTeamFieldAgentTest.php`

This is the core behavioural change. The branch must be unit-pinned in three states: individual agent (existing key), team agent (new key), team agent with missing setting (falls back to `0`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ReferralBonusTeamFieldAgentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReferralBonusTeamFieldAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeReferralFor(User $sharer): Referral
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 1]);

        $code = ReferralCode::create([
            'influencer_id' => $sharer->id,
            'is_active' => true,
            'prefix' => ReferralCode::getPrefixForRole($sharer->role),
        ]);

        $vendorApp = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => VendorApplication::STATUS_APPROVED,
            'tier' => 1,
            'final_amount' => 200.00,
            'referral_code_id' => $code->id,
            'referral_code_used' => $code->code,
        ]);

        return Referral::create([
            'referral_code_id' => $code->id,
            'influencer_id' => $sharer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $vendorApp->id,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function test_individual_field_agent_uses_individual_setting_key(): void
    {
        Cache::flush();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Setting::set('referral_bonus_field_agent_team_pct', '50', 'number');

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => false,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // 30% of 200 = 60 GHS
        $this->assertEqualsWithDelta(60.00, (float) $referral->fresh()->earned_amount, 0.01);

        $earning = Earning::where('user_id', $agent->id)->firstOrFail();
        $this->assertEqualsWithDelta(60.00, (float) $earning->amount, 0.01);
    }

    public function test_team_field_agent_uses_team_setting_key(): void
    {
        Cache::flush();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Setting::set('referral_bonus_field_agent_team_pct', '50', 'number');

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // 50% of 200 = 100 GHS
        $this->assertEqualsWithDelta(100.00, (float) $referral->fresh()->earned_amount, 0.01);

        $earning = Earning::where('user_id', $agent->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.00, (float) $earning->amount, 0.01);
    }

    public function test_team_field_agent_falls_back_to_zero_when_team_setting_missing(): void
    {
        Cache::flush();
        Setting::where('key', 'referral_bonus_field_agent_team_pct')->delete();
        Setting::set('referral_bonus_field_agent_pct', '30', 'number');
        Cache::flush();

        $agent = User::factory()->create([
            'role' => 'field_agent',
            'is_team_field_agent' => true,
        ]);
        $referral = $this->makeReferralFor($agent);

        app(ReferralService::class)->activateReferral($referral->vendorApplication);

        // Falls back to 0; no Earning row should exist (creation gated on amount > 0)
        $this->assertEqualsWithDelta(0.00, (float) $referral->fresh()->earned_amount, 0.01);
        $this->assertSame(0, Earning::where('user_id', $agent->id)->count());
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=ReferralBonusTeamFieldAgentTest
```

Expected: `test_team_field_agent_uses_team_setting_key` fails — the service still reads `referral_bonus_field_agent_pct` for both branches, so the team agent gets 60.00 (30% of 200) instead of the expected 100.00. The other two tests should pass.

- [ ] **Step 3: Add the branch in `ReferralService::activateReferral`**

In `app/Services/ReferralService.php`, locate this line inside `activateReferral`:

```php
$percentage = (float) Setting::get("referral_bonus_{$sharer->role}_pct", 0);
```

Replace it with:

```php
$settingKey = $sharer->isTeamFieldAgent()
    ? 'referral_bonus_field_agent_team_pct'
    : "referral_bonus_{$sharer->role}_pct";

$percentage = (float) Setting::get($settingKey, 0);
```

- [ ] **Step 4: Run the tests to confirm they all pass**

```bash
docker compose exec laravel.test php artisan test --compact --filter=ReferralBonusTeamFieldAgentTest
```

Expected: 3 passed.

- [ ] **Step 5: Run the full referral test suite to confirm no regression**

```bash
docker compose exec laravel.test php artisan test --compact --filter=Referral
```

Expected: every Referral* test still passes. The branch only diverges for `field_agent` with `is_team_field_agent = true`; everyone else takes the existing code path.

- [ ] **Step 6: Commit**

```bash
git add app/Services/ReferralService.php tests/Feature/ReferralBonusTeamFieldAgentTest.php
git commit -m "feat(referrals): branch field-agent commission rate on team flag"
```

---

## Task 13: Admin field-agent-applications index — `type` filter and Team badge

**Files:**
- Modify: `app/Http/Controllers/Admin/FieldAgentApplicationController.php`
- Modify: `resources/js/pages/admin/field-agent-applications/index.tsx`
- Modify: `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`:

```php
public function test_admin_index_filters_by_type_team(): void
{
    FieldAgentApplication::factory()->team()->pending()->count(2)->create();
    FieldAgentApplication::factory()->pending()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard/field-agent-applications?type=team')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('applications.total', 2)->etc());
}

public function test_admin_index_filters_by_type_individual(): void
{
    FieldAgentApplication::factory()->team()->pending()->count(2)->create();
    FieldAgentApplication::factory()->pending()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard/field-agent-applications?type=individual')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('applications.total', 3)->etc());
}

public function test_admin_index_ignores_invalid_type_filter(): void
{
    FieldAgentApplication::factory()->team()->pending()->count(2)->create();
    FieldAgentApplication::factory()->pending()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard/field-agent-applications?type=banana')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('applications.total', 5)->etc());
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationAdminTest
```

Expected: the three new tests fail because the controller does not yet apply a `type` filter — all three return 5 applications.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Admin/FieldAgentApplicationController.php`, replace the existing `index` method body with:

```php
public function index(Request $request): Response
{
    $query = FieldAgentApplication::query()
        ->with(['region:id,name', 'city:id,name', 'reviewer:id,name']);

    if ($status = $request->string('status')->toString()) {
        $query->where('status', $status);
    }
    if ($regionId = $request->integer('region_id')) {
        $query->where('region_id', $regionId);
    }

    $type = $request->string('type')->toString();
    if (in_array($type, ['individual', 'team'], true)) {
        $query->where('is_team', $type === 'team');
    }

    $applications = $query->latest()->paginate(20)->withQueryString();

    return Inertia::render('admin/field-agent-applications/index', [
        'applications' => $applications,
        'filters' => $request->only(['status', 'region_id', 'type']),
        'statuses' => collect(FieldAgentApplicationStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
    ]);
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
docker compose exec laravel.test php artisan test --compact --filter=FieldAgentApplicationAdminTest
```

Expected: all tests pass.

- [ ] **Step 5: Update the index view — extend `Application` and `filters` types**

In `resources/js/pages/admin/field-agent-applications/index.tsx`, update the `Application` type to include `is_team`:

```tsx
type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    status: string;
    is_team: boolean;
    created_at: string;
    region?: { name: string };
    city?: { name: string };
};
```

Update the `Props.filters` type to include `type`:

```tsx
filters: { status?: string; region_id?: number; type?: 'individual' | 'team' };
```

- [ ] **Step 6: Add the type filter Select alongside the status filter**

In the filters `<Box>` (just below the existing status `<Select>`), add a new state and Select. Near the existing `useState` for status, add:

```tsx
const [type, setType] = useState(filters.type ?? '');
```

Update `applyFilter` to forward the type as well, and add a new `applyType` handler:

```tsx
const applyType = (newType: string) => {
    setType(newType);
    router.get(
        '/dashboard/field-agent-applications',
        {
            status: status || undefined,
            type: newType || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
};
```

Update the existing `applyFilter` to keep the `type` in the URL:

```tsx
const applyFilter = (newStatus: string) => {
    setStatus(newStatus);
    router.get(
        '/dashboard/field-agent-applications',
        {
            status: newStatus || undefined,
            type: type || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
};
```

After the existing status `<Select>` and before the closing `</Box>` of the filters container, add:

```tsx
<Select
    value={type || 'all'}
    onValueChange={(v) => applyType(v === 'all' ? '' : v)}
>
    <SelectTrigger className="w-48">
        <SelectValue placeholder="All types" />
    </SelectTrigger>
    <SelectContent>
        <SelectItem value="all">All Types</SelectItem>
        <SelectItem value="individual">Individual</SelectItem>
        <SelectItem value="team">Team</SelectItem>
    </SelectContent>
</Select>
```

- [ ] **Step 7: Render the Team badge in the name cell**

Locate the table cell that renders the applicant name:

```tsx
<Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
    {a.first_name} {a.last_name}
</Box>
```

Replace it with:

```tsx
<Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
    <Box sx={{ display: 'inline-flex', alignItems: 'center', gap: 1 }}>
        <span>{a.first_name} {a.last_name}</span>
        {a.is_team && (
            <Badge variant="secondary">Team</Badge>
        )}
    </Box>
</Box>
```

- [ ] **Step 8: Build and smoke-test**

```bash
docker compose exec laravel.test pnpm run build
```

Then visit `http://localhost:8082/dashboard/field-agent-applications`. Verify:
1. Existing rows still render.
2. Submit a team application via the public registration form, then refresh the admin list — the new row shows a "Team" badge.
3. The "All types / Individual / Team" filter narrows the list correctly.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/FieldAgentApplicationController.php resources/js/pages/admin/field-agent-applications/index.tsx tests/Feature/Admin/FieldAgentApplicationAdminTest.php
git commit -m "feat(admin): filter field-agent applications by type and badge teams"
```

---

## Task 14: Admin field-agent-applications show — registration-type row

**Files:**
- Modify: `resources/js/pages/admin/field-agent-applications/show.tsx`

The backend serialises `is_team` automatically via `$fieldAgentApplication->toArray()` in `show()` (already verified during exploration), so no controller change is needed.

- [ ] **Step 1: Update the `Application` type**

In `resources/js/pages/admin/field-agent-applications/show.tsx`, append to the `Application` type definition:

```tsx
is_team: boolean;
```

So the type becomes:

```tsx
type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    location: string;
    ghana_card_number: string;
    status: string;
    is_team: boolean;
    region?: { name: string };
    city?: { name: string };
    reviewer?: { name: string } | null;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
    ghana_card_image_url: string;
    ghana_card_back_image_url: string | null;
    selfie_url: string;
};
```

- [ ] **Step 2: Add the registration-type field to the Identity section**

Find the existing Identity section:

```tsx
<Section title="Identity">
    <Field
        label="Ghana card number"
        value={application.ghana_card_number}
    />
</Section>
```

Replace with:

```tsx
<Section title="Identity">
    <Field
        label="Registration type"
        value={application.is_team ? 'Team' : 'Individual'}
    />
    <Field
        label="Ghana card number"
        value={application.ghana_card_number}
    />
</Section>
```

- [ ] **Step 3: Build and smoke-test**

```bash
docker compose exec laravel.test pnpm run build
```

Open a team application detail page (e.g. `/dashboard/field-agent-applications/{id}` for the team application created in Task 13). Confirm "Registration type: Team" appears in the Identity section.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/admin/field-agent-applications/show.tsx
git commit -m "feat(admin): show registration type on field-agent application detail"
```

---

## Task 15: Users index — Team badge for field agents

**Files:**
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `resources/js/pages/users/index.tsx`

- [ ] **Step 1: Update the controller payload**

In `app/Http/Controllers/UserController.php::index`, the existing closure mapping a User to its row data already conditionally adds `visits_completed_count` for field agents. Extend the same conditional to include `is_team_field_agent`:

```php
if ($activeRole === 'field_agent') {
    $totalVisits = $user->vendorVisitsAsAgent()->whereIn('status', ['submitted'])->count();

    $data['visits_completed_count'] = $totalVisits;
    $data['pass_rate'] = null; // pass rate is deprecated
    $data['is_team_field_agent'] = (bool) $user->is_team_field_agent;
}
```

Also add `is_team_field_agent` to the `select(...)` statement at the top of `index()`:

```php
$query = User::query()
    ->select(['id', 'name', 'email', 'phone', 'role', 'is_team_field_agent', 'email_verified_at', 'created_at']);
```

- [ ] **Step 2: Update the frontend type**

In `resources/js/pages/users/index.tsx`, find where `users.data[i]` is consumed. The `PaginatedUsers` type comes from `@/types`. To avoid editing the global type, locate the per-user row type used inside the table cells. If the file consumes `PaginatedUsers['data'][number]` directly, add a local widened type at the top of the component:

```tsx
type UserRow = PaginatedUsers['data'][number] & {
    is_team_field_agent?: boolean;
};
```

Cast or assert as needed when iterating the rows: `users.data.map((u: UserRow) => ...)`.

- [ ] **Step 3: Render the badge next to the role pill**

Find the table cell that renders the role pill — search the file for the existing `<Chip>` with role data. The render currently looks like:

```tsx
<Chip label={formatRole(user.role)} color={getRoleBadgeColor(user.role)} size="small" />
```

Replace with:

```tsx
<Box sx={{ display: 'inline-flex', alignItems: 'center', gap: 1 }}>
    <Chip label={formatRole(user.role)} color={getRoleBadgeColor(user.role)} size="small" />
    {user.role === 'field_agent' && (user as UserRow).is_team_field_agent && (
        <Chip label="Team" size="small" variant="outlined" />
    )}
</Box>
```

- [ ] **Step 4: Build and smoke-test**

```bash
docker compose exec laravel.test pnpm run build
```

Visit `http://localhost:8082/dashboard/users?role=field_agent`. Approve the team application from earlier (admin field-agent-applications show → Approve), then return to the users list. The newly-approved team field agent's row should show the "Team" badge next to the role pill.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/UserController.php resources/js/pages/users/index.tsx
git commit -m "feat(admin): badge team field agents in users list"
```

---

## Task 16: Final verification

**Files:** none modified — verification only.

- [ ] **Step 1: Run Pint to format any new code**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty --format agent
```

Expected: clean. If anything reformats, stage and commit:

```bash
git add -u
git commit -m "style: pint formatting"
```

- [ ] **Step 2: Run the full test suite**

```bash
docker compose exec laravel.test php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 3: Build the frontend one last time**

```bash
docker compose exec laravel.test pnpm run build
```

Expected: clean build.

- [ ] **Step 4: End-to-end smoke test**

In the browser:

1. Visit `/field-agents/register` (logged out). Walk the wizard and tick "I am registering as a team leader." Submit. Confirm "Registration type: Team" appears in the review step.
2. Log in as super-admin. Visit `/dashboard/field-agent-applications`. Confirm the new application has a "Team" badge.
3. Filter by `type=Team`; confirm only team applications show.
4. Open the team application detail. Confirm "Registration type: Team" in the Identity section. Approve.
5. Visit `/dashboard/users?role=field_agent`. Confirm the newly-approved user shows a "Team" badge next to the role pill.
6. Visit `/settings/vendor-onboarding`. Set `Field Agent (Team)` to `40`. Save. Reload. Confirm `40.00` persisted.
7. (Optional, if a referred-vendor approval flow is reachable in dev) Approve a vendor whose referral code belongs to the team field agent and confirm the resulting Earning row reflects the team rate (40% of `final_amount`).

- [ ] **Step 5: Push the branch (do not merge)**

```bash
git push -u origin feat/team-field-agent-registration-spec
```

The user reviews and merges.

---

## Self-review notes (already applied)

Spec-coverage scan: every section in the spec maps to at least one task above. §3 → Tasks 2, 3, 5, 6. §4 → Tasks 7, 8, 9. §5 → Task 10. §6 → Tasks 4, 11. §7 → Task 12. §8 → Tasks 13, 14, 15. §9 → no code (admin uses the badge as a visual cue). §10 (operational notes) → Task 3 (CONCURRENTLY) and Task 4 (seed). §11 (testing) → tests inside Tasks 5, 6, 7, 10, 11, 12, 13. §12 (implementation order) → drives Task 1–16 ordering.

Placeholder scan: no "TBD", no "implement appropriate", no abstract steps. Every code block contains the exact text to write or replace.

Type consistency: `is_team` (column), `isTeam()` (method on application), `is_team_field_agent` (column), `isTeamFieldAgent()` (method on user), `referral_bonus_field_agent_team_pct` (setting key) — all spelled identically across tasks.
