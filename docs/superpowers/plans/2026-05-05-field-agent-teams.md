# Field Agent Teams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a team-lead field agent self-service create login accounts for their members, who can run questionnaires for vendors but cannot see earnings/payouts/targets; all earnings continue to credit the lead.

**Architecture:** Self-referencing `users` table (`parent_user_id`) for a flat lead → member hierarchy. Members have no referral code; the application's existing `referral_code_id` (always the lead's) drives earning attribution. A new `vendor_applications.onboarded_by_user_id` column captures who actually claimed the vendor for per-member breakdown. Authorization uses a `manageTeam` Gate plus a `TeamMemberPolicy`. First-login forced password change is enforced by an `EnforcePasswordChange` middleware in the dashboard stack. Inertia shared props expose `parent_user_id`, `is_team_field_agent`, and `must_change_password` on `auth.user` for client gating; server-side gates remain authoritative.

**Tech Stack:** Laravel 12, Postgres, Inertia v2, React 19 (TypeScript), Wayfinder, Sanctum + Fortify, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-05-05-field-agent-teams-design.md`

**Branch:** `feat/field-agent-teams` (already created off `origin/main`, contains spec commit `fc91a92`).

---

## File Structure

**Created**

- `database/migrations/2026_05_05_120000_add_team_member_fields_to_users_and_vendor_applications.php` — adds `parent_user_id`, `is_active`, `must_change_password`, `location` to `users`; adds `onboarded_by_user_id` to `vendor_applications`.
- `app/Http/Controllers/FieldAgent/TeamMemberController.php` — `index`, `create`, `store`, `show`, `update` (toggle `is_active`).
- `app/Http/Requests/FieldAgent/StoreTeamMemberRequest.php` — validation + `authorize()` for member creation.
- `app/Http/Middleware/EnforcePasswordChange.php` — redirects users with `must_change_password=true` to `settings/password` on every protected request.
- `app/Policies/TeamMemberPolicy.php` — `view`, `update` ownership checks.
- `resources/js/pages/field-agent/team/index.tsx` — member list.
- `resources/js/pages/field-agent/team/new.tsx` — create form.
- `resources/js/pages/field-agent/team/show.tsx` — member profile + onboarded vendors + deactivate toggle.
- `tests/Feature/FieldAgent/Team/CreateTeamMemberTest.php`
- `tests/Feature/FieldAgent/Team/ListTeamMembersTest.php`
- `tests/Feature/FieldAgent/Team/ShowTeamMemberTest.php`
- `tests/Feature/FieldAgent/Team/ToggleTeamMemberActiveTest.php`
- `tests/Feature/FieldAgent/Team/ForcePasswordChangeTest.php`
- `tests/Feature/FieldAgent/Team/VendorAttributionTest.php`
- `tests/Feature/FieldAgent/Team/MemberDashboardScopingTest.php`

**Modified**

- `app/Models/User.php` — `$fillable`, `casts()`, `lead()`, `teamMembers()`, `isLead()`, `isTeamMember()`.
- `database/factories/UserFactory.php` — `lead()` and `teamMember(User $lead)` states.
- `app/Providers/AppServiceProvider.php` — `Gate::define('manageTeam', ...)`, `Gate::policy(User::class, TeamMemberPolicy::class)` (cannot conflict — existing code has no `User` policy).
- `app/Providers/FortifyServiceProvider.php` — `authenticateUsing` rejects `is_active=false`.
- `app/Http/Middleware/HandleInertiaRequests.php` — share `parent_user_id`, `is_team_field_agent`, `must_change_password`, `is_active` on `auth.user`.
- `app/Http/Middleware/EnsureDashboardAccess.php` — block members from `/field-agent/{earnings,payouts,targets,verification,terms,team*}`.
- `app/Http/Controllers/Settings/PasswordController.php` — clears `must_change_password` after successful update.
- `app/Http/Controllers/FieldAgent/VendorVisitsController.php` — `start()` allows team members of the lead and sets `vendor_applications.onboarded_by_user_id` on first claim; `index()` filters by `onboarded_by_user_id` for members.
- `app/Http/Controllers/FieldAgentDashboardController.php` — for members, scope `computeVendorStats`/`computeRecentVendors` to `onboarded_by_user_id`, skip `getOrCreateReferralCode`, omit money fields.
- `bootstrap/app.php` — alias `enforce-password-change` (and append it to the `web` middleware stack so the Inertia/dashboard groups inherit it).
- `routes/web.php` — five routes inside the `field-agent.` group, gated `can:manageTeam`.
- `resources/js/components/app-sidebar.tsx` — add "Team" entry for `is_team_field_agent && !parent_user_id`; do not add it for members.
- `resources/js/pages/field-agent/dashboard.tsx` — render `ReferralCodeCard` only when not a member; hide money tiles for members.

---

## Task 1: Add factory states for lead and team member

**Files:**
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Unit/UserFactoryTeamStatesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/UserFactoryTeamStatesTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTeamStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_state_creates_team_field_agent_with_no_parent(): void
    {
        $lead = User::factory()->lead()->create();

        $this->assertSame('field_agent', $lead->role);
        $this->assertTrue($lead->is_team_field_agent);
        $this->assertNull($lead->parent_user_id);
        $this->assertTrue((bool) $lead->is_active);
        $this->assertFalse((bool) $lead->must_change_password);
    }

    public function test_team_member_state_creates_member_under_a_lead(): void
    {
        $lead = User::factory()->lead()->create();

        $member = User::factory()->teamMember($lead)->create();

        $this->assertSame('field_agent', $member->role);
        $this->assertFalse((bool) $member->is_team_field_agent);
        $this->assertSame($lead->id, $member->parent_user_id);
        $this->assertTrue((bool) $member->is_active);
        $this->assertTrue((bool) $member->must_change_password);
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `php artisan test --compact --filter=UserFactoryTeamStatesTest`
Expected: FAIL — `BadMethodCallException: Call to undefined method ... lead()`.

- [ ] **Step 3: Add the states**

Append to `database/factories/UserFactory.php` after the existing `employee()` method (before the closing brace):

```php
    /**
     * Indicate that the user is a team-lead field agent.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'field_agent',
            'is_team_field_agent' => true,
            'parent_user_id' => null,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * Indicate that the user is a team-member field agent under the given lead.
     */
    public function teamMember(\App\Models\User $lead): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'field_agent',
            'is_team_field_agent' => false,
            'parent_user_id' => $lead->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }
```

- [ ] **Step 4: Run the test (still expected to fail until migration runs)**

The test will still fail because the columns don't exist yet. Mark this task done and move on — Task 2 adds the columns, then re-run.

- [ ] **Step 5: Commit**

```bash
git add database/factories/UserFactory.php tests/Unit/UserFactoryTeamStatesTest.php
git commit -m "test(field-agent-teams): add UserFactory lead/teamMember states"
```

---

## Task 2: Migration — add columns to users and vendor_applications

**Files:**
- Create: `database/migrations/2026_05_05_120000_add_team_member_fields_to_users_and_vendor_applications.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('location', 255)->nullable();
            $table->index('parent_user_id', 'users_parent_user_id_index');
        });

        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->foreignId('onboarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('onboarded_by_user_id', 'vendor_applications_onboarded_by_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropForeign(['onboarded_by_user_id']);
            $table->dropIndex('vendor_applications_onboarded_by_user_id_index');
            $table->dropColumn('onboarded_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropIndex('users_parent_user_id_index');
            $table->dropColumn(['parent_user_id', 'is_active', 'must_change_password', 'location']);
        });
    }
};
```

- [ ] **Step 2: Run the previous test to confirm migration unblocks it**

Run: `php artisan test --compact --filter=UserFactoryTeamStatesTest`
Expected: PASS — both tests pass once the columns exist.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_05_120000_add_team_member_fields_to_users_and_vendor_applications.php
git commit -m "feat(field-agent-teams): add team member columns to users and vendor_applications"
```

---

## Task 3: Update User model — fillable, casts, relationships, helpers

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/UserTeamRelationshipsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTeamRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_has_team_members_relation(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->teamMember($lead)->count(2)->create();

        $this->assertCount(2, $lead->teamMembers);
        $this->assertTrue($lead->isLead());
        $this->assertFalse($lead->isTeamMember());
    }

    public function test_member_has_lead_relation(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->assertTrue($member->lead->is($lead));
        $this->assertFalse($member->isLead());
        $this->assertTrue($member->isTeamMember());
    }

    public function test_is_active_and_must_change_password_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => 1, 'must_change_password' => 0]);

        $this->assertSame(true, $user->is_active);
        $this->assertSame(false, $user->must_change_password);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=UserTeamRelationshipsTest`
Expected: FAIL — `Call to undefined method App\Models\User::teamMembers()`.

- [ ] **Step 3: Update `$fillable`**

In `app/Models/User.php`, add to the `$fillable` list (after `is_team_field_agent`):

```php
        'is_team_field_agent',
        'parent_user_id',
        'is_active',
        'must_change_password',
        'location',
```

- [ ] **Step 4: Update `casts()`**

In the `casts()` method, add:

```php
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'parent_user_id' => 'integer',
```

- [ ] **Step 5: Add relationships and helpers**

After the existing `isTeamFieldAgent()` method (~line 421), add:

```php
    /**
     * The team-lead this user reports to. Null for leads/non-members.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, $this>
     */
    public function lead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_user_id');
    }

    /**
     * Members reporting to this user as a team lead.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<self, $this>
     */
    public function teamMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_user_id');
    }

    /**
     * Whether this user is a field-agent team lead (can manage members).
     */
    public function isLead(): bool
    {
        return $this->isFieldAgent()
            && (bool) $this->is_team_field_agent
            && $this->parent_user_id === null;
    }

    /**
     * Whether this user is a team member under a field-agent lead.
     */
    public function isTeamMember(): bool
    {
        return $this->isFieldAgent() && $this->parent_user_id !== null;
    }
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --compact --filter=UserTeamRelationshipsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php tests/Unit/UserTeamRelationshipsTest.php
git commit -m "feat(field-agent-teams): add lead/teamMembers relations and helpers to User"
```

---

## Task 4: Define `manageTeam` gate

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/ManageTeamGateTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManageTeamGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_manage_team(): void
    {
        $lead = User::factory()->lead()->create();
        $this->assertTrue(Gate::forUser($lead)->allows('manageTeam'));
    }

    public function test_solo_field_agent_cannot_manage_team(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);
        $this->assertFalse(Gate::forUser($solo)->allows('manageTeam'));
    }

    public function test_member_cannot_manage_team(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $this->assertFalse(Gate::forUser($member)->allows('manageTeam'));
    }

    public function test_admin_cannot_manage_team_via_this_gate(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertFalse(Gate::forUser($admin)->allows('manageTeam'));
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=ManageTeamGateTest`
Expected: FAIL — gate undefined returns false for all, but `test_lead_can_manage_team` fails.

- [ ] **Step 3: Define the gate**

In `app/Providers/AppServiceProvider.php` `boot()`, after the existing `Gate::define('viewApiDocs', ...)` (~line 99):

```php
        Gate::define('manageTeam', function (\App\Models\User $user) {
            return $user->isFieldAgent()
                && (bool) $user->is_team_field_agent
                && $user->parent_user_id === null;
        });
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=ManageTeamGateTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Unit/ManageTeamGateTest.php
git commit -m "feat(field-agent-teams): define manageTeam gate"
```

---

## Task 5: TeamMemberPolicy — per-member ownership

**Files:**
- Create: `app/Policies/TeamMemberPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register policy)
- Test: `tests/Unit/TeamMemberPolicyTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\TeamMemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_view_and_update_own_member(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $policy = new TeamMemberPolicy;

        $this->assertTrue($policy->view($lead, $member));
        $this->assertTrue($policy->update($lead, $member));
    }

    public function test_lead_cannot_view_or_update_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();
        $policy = new TeamMemberPolicy;

        $this->assertFalse($policy->view($leadA, $memberOfB));
        $this->assertFalse($policy->update($leadA, $memberOfB));
    }

    public function test_solo_or_member_cannot_view_or_update(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);
        $policy = new TeamMemberPolicy;

        $this->assertFalse($policy->view($solo, $member));
        $this->assertFalse($policy->update($member, $member));
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=TeamMemberPolicyTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the policy**

`app/Policies/TeamMemberPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;

class TeamMemberPolicy
{
    /**
     * A lead may view a member iff that member's parent is the lead.
     */
    public function view(User $user, User $member): bool
    {
        return $this->isLead($user)
            && $member->parent_user_id === $user->id;
    }

    /**
     * Same constraint as view; only `is_active` is mutable here.
     */
    public function update(User $user, User $member): bool
    {
        return $this->view($user, $member);
    }

    private function isLead(User $user): bool
    {
        return $user->isFieldAgent()
            && (bool) $user->is_team_field_agent
            && $user->parent_user_id === null;
    }
}
```

- [ ] **Step 4: Register the policy**

In `app/Providers/AppServiceProvider.php` `boot()`, after the existing `Gate::policy(\App\Models\UserPayoutDetail::class, ...)` line:

```php
        Gate::policy(\App\Models\User::class, \App\Policies\TeamMemberPolicy::class);
```

(Note: this only kicks in when authorizing against a `User` model. The existing codebase has no other `User` policy, so no conflict.)

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=TeamMemberPolicyTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/TeamMemberPolicy.php app/Providers/AppServiceProvider.php tests/Unit/TeamMemberPolicyTest.php
git commit -m "feat(field-agent-teams): add TeamMemberPolicy"
```

---

## Task 6: StoreTeamMemberRequest — validation

**Files:**
- Create: `app/Http/Requests/FieldAgent/StoreTeamMemberRequest.php`
- Test: covered indirectly by Task 7's controller test (`CreateTeamMemberTest`).

- [ ] **Step 1: Create the form request**

```php
<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeam') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone((string) $this->input('phone')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'regex:/^\+233\d{9}$/', Rule::unique('users', 'phone')],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Ghana phone number (e.g. 0551234567 or +233551234567).',
        ];
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233'.substr($digits, 1);
        }

        return $raw;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/FieldAgent/StoreTeamMemberRequest.php
git commit -m "feat(field-agent-teams): add StoreTeamMemberRequest"
```

---

## Task 7: TeamMemberController@store — create a member

**Files:**
- Create: `app/Http/Controllers/FieldAgent/TeamMemberController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FieldAgent/Team/CreateTeamMemberTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_creates_member_with_phone_as_password(): void
    {
        $lead = User::factory()->lead()->create();

        $response = $this->actingAs($lead)->post('/field-agent/team', [
            'name' => 'Member One',
            'email' => 'm1@example.com',
            'phone' => '0551234567',
            'location' => 'Accra',
        ]);

        $response->assertRedirect('/field-agent/team');

        $member = User::where('email', 'm1@example.com')->firstOrFail();
        $this->assertSame('field_agent', $member->role);
        $this->assertSame($lead->id, $member->parent_user_id);
        $this->assertFalse((bool) $member->is_team_field_agent);
        $this->assertTrue((bool) $member->is_active);
        $this->assertTrue((bool) $member->must_change_password);
        $this->assertSame('+233551234567', $member->phone);
        $this->assertSame('Accra', $member->location);
        $this->assertTrue(Hash::check('+233551234567', $member->password));
    }

    public function test_solo_field_agent_cannot_create_members(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);

        $this->actingAs($solo)->post('/field-agent/team', [
            'name' => 'X', 'email' => 'x@e.com', 'phone' => '0551112222', 'location' => 'A',
        ])->assertForbidden();
    }

    public function test_member_cannot_create_sub_members(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->post('/field-agent/team', [
            'name' => 'X', 'email' => 'x@e.com', 'phone' => '0551112222', 'location' => 'A',
        ])->assertForbidden();
    }

    public function test_duplicate_email_or_phone_returns_validation_error(): void
    {
        $lead = User::factory()->lead()->create();
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '+233551112222']);

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'taken@example.com', 'phone' => '0552223333', 'location' => 'A',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'fresh@example.com', 'phone' => '0551112222', 'location' => 'A',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_invalid_phone_format_rejected(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)
            ->from('/field-agent/team/new')
            ->post('/field-agent/team', [
                'name' => 'X', 'email' => 'x@e.com', 'phone' => 'not-a-number', 'location' => 'A',
            ])
            ->assertSessionHasErrors('phone');
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=CreateTeamMemberTest`
Expected: FAIL — route not defined / 404.

- [ ] **Step 3: Create the controller skeleton with `store`**

```php
<?php

namespace App\Http\Controllers\FieldAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StoreTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class TeamMemberController extends Controller
{
    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email')->lower(),
            'phone' => $request->string('phone'),
            'location' => $request->string('location'),
            'role' => 'field_agent',
            'is_team_field_agent' => false,
            'parent_user_id' => $request->user()->id,
            'is_active' => true,
            'must_change_password' => true,
            'password' => Hash::make((string) $request->string('phone')),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        return redirect('/field-agent/team')
            ->with('success', 'Team member added. Their default password is their phone number.');
    }
}
```

- [ ] **Step 4: Register the POST route**

In `routes/web.php`, inside the existing `field-agent.` group (after the `visits` sub-group, before the SPA catch-all at line 294):

```php
    Route::middleware('can:manageTeam')->prefix('team')->name('team.')->group(function () {
        Route::post('/', [\App\Http\Controllers\FieldAgent\TeamMemberController::class, 'store'])->name('store');
    });
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=CreateTeamMemberTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FieldAgent/TeamMemberController.php routes/web.php tests/Feature/FieldAgent/Team/CreateTeamMemberTest.php
git commit -m "feat(field-agent-teams): TeamMemberController@store creates members"
```

---

## Task 8: TeamMemberController@index — list members

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/TeamMemberController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FieldAgent/Team/ListTeamMembersTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ListTeamMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_sees_only_own_members(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $ownMembers = User::factory()->teamMember($leadA)->count(2)->create();
        User::factory()->teamMember($leadB)->count(3)->create();

        $response = $this->actingAs($leadA)->get('/field-agent/team');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/team/index')
            ->has('members', 2)
            ->where('members.0.parent_user_id', $leadA->id)
        );
    }

    public function test_solo_field_agent_forbidden(): void
    {
        $solo = User::factory()->fieldAgent()->create(['is_team_field_agent' => false]);

        $this->actingAs($solo)->get('/field-agent/team')->assertForbidden();
    }

    public function test_member_forbidden(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)->get('/field-agent/team')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=ListTeamMembersTest`
Expected: FAIL — 404 or method not found.

- [ ] **Step 3: Add `index` to the controller**

Add to `app/Http/Controllers/FieldAgent/TeamMemberController.php`:

```php
    public function index(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $members = $request->user()->teamMembers()
            ->select(['id', 'parent_user_id', 'name', 'email', 'phone', 'location', 'is_active', 'must_change_password', 'created_at'])
            ->addSelect([
                'vendors_onboarded' => \App\Models\VendorApplication::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('onboarded_by_user_id', 'users.id'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return \Inertia\Inertia::render('field-agent/team/index', [
            'members' => $members,
        ]);
    }
```

(The `vendors_onboarded` subquery counts vendor applications attributed to the member; the per-vendor breakdown by status lives on the show page.)

- [ ] **Step 4: Add the GET route**

Inside the `team` sub-group in `routes/web.php`:

```php
        Route::get('/', [\App\Http\Controllers\FieldAgent\TeamMemberController::class, 'index'])->name('index');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=ListTeamMembersTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FieldAgent/TeamMemberController.php routes/web.php tests/Feature/FieldAgent/Team/ListTeamMembersTest.php
git commit -m "feat(field-agent-teams): TeamMemberController@index lists own members"
```

---

## Task 9: TeamMemberController@create — render the new-member form

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/TeamMemberController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add `create` method**

```php
    public function create(): \Inertia\Response
    {
        return \Inertia\Inertia::render('field-agent/team/new');
    }
```

- [ ] **Step 2: Add the GET route**

```php
        Route::get('/new', [\App\Http\Controllers\FieldAgent\TeamMemberController::class, 'create'])->name('create');
```

- [ ] **Step 3: Smoke test**

Add to `CreateTeamMemberTest`:

```php
    public function test_lead_can_render_new_member_form(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)->get('/field-agent/team/new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('field-agent/team/new'));
    }
```

Run: `php artisan test --compact --filter=CreateTeamMemberTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FieldAgent/TeamMemberController.php routes/web.php tests/Feature/FieldAgent/Team/CreateTeamMemberTest.php
git commit -m "feat(field-agent-teams): TeamMemberController@create renders new-member form"
```

---

## Task 10: TeamMemberController@show — member detail with onboarded vendors

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/TeamMemberController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FieldAgent/Team/ShowTeamMemberTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ShowTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_sees_own_member_with_onboarded_vendors(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'onboarded_by_user_id' => $member->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $response = $this->actingAs($lead)->get("/field-agent/team/{$member->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/team/show')
            ->where('member.id', $member->id)
            ->has('vendors', 1)
            ->where('vendors.0.id', $application->id)
            ->missing('vendors.0.amount')
        );
    }

    public function test_lead_cannot_view_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();

        $this->actingAs($leadA)->get("/field-agent/team/{$memberOfB->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=ShowTeamMemberTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Add `show` method**

```php
    public function show(\Illuminate\Http\Request $request, \App\Models\User $member): \Inertia\Response
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $member);

        $vendors = \App\Models\VendorApplication::query()
            ->with('user:id,name,business_name')
            ->where('onboarded_by_user_id', $member->id)
            ->latest('created_at')
            ->get()
            ->map(fn (\App\Models\VendorApplication $app) => [
                'id' => $app->id,
                'business_name' => $app->user?->business_name ?: ($app->user?->name ?? ''),
                'status' => $app->status,
                'created_at' => $app->created_at?->toIso8601String(),
            ]);

        return \Inertia\Inertia::render('field-agent/team/show', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'location' => $member->location,
                'is_active' => (bool) $member->is_active,
                'must_change_password' => (bool) $member->must_change_password,
                'created_at' => $member->created_at?->toIso8601String(),
            ],
            'vendors' => $vendors,
        ]);
    }
```

- [ ] **Step 4: Add the GET route**

```php
        Route::get('/{member}', [\App\Http\Controllers\FieldAgent\TeamMemberController::class, 'show'])->name('show');
```

(Place this AFTER `/new` so route ordering doesn't capture `new` as a member id.)

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=ShowTeamMemberTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FieldAgent/TeamMemberController.php routes/web.php tests/Feature/FieldAgent/Team/ShowTeamMemberTest.php
git commit -m "feat(field-agent-teams): TeamMemberController@show with onboarded vendors"
```

---

## Task 11: TeamMemberController@update — toggle is_active

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/TeamMemberController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FieldAgent/Team/ToggleTeamMemberActiveTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ToggleTeamMemberActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_deactivates_then_reactivates_member(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", ['is_active' => false])
            ->assertRedirect("/field-agent/team/{$member->id}");
        $this->assertFalse((bool) $member->fresh()->is_active);

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", ['is_active' => true])
            ->assertRedirect("/field-agent/team/{$member->id}");
        $this->assertTrue((bool) $member->fresh()->is_active);
    }

    public function test_lead_cannot_toggle_other_leads_member(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create();

        $this->actingAs($leadA)->patch("/field-agent/team/{$memberOfB->id}", ['is_active' => false])
            ->assertForbidden();
        $this->assertTrue((bool) $memberOfB->fresh()->is_active);
    }

    public function test_other_fields_are_ignored(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create([
            'name' => 'Original',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($lead)->patch("/field-agent/team/{$member->id}", [
            'is_active' => false,
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
            'parent_user_id' => 99999,
        ])->assertRedirect();

        $fresh = $member->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame('original@example.com', $fresh->email);
        $this->assertSame($lead->id, $fresh->parent_user_id);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=ToggleTeamMemberActiveTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Add `update` method**

```php
    public function update(\Illuminate\Http\Request $request, \App\Models\User $member): \Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $member);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $member->update(['is_active' => $validated['is_active']]);

        return redirect("/field-agent/team/{$member->id}")
            ->with('success', $validated['is_active'] ? 'Member reactivated.' : 'Member deactivated.');
    }
```

- [ ] **Step 4: Add the PATCH route**

```php
        Route::patch('/{member}', [\App\Http\Controllers\FieldAgent\TeamMemberController::class, 'update'])->name('update');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=ToggleTeamMemberActiveTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FieldAgent/TeamMemberController.php routes/web.php tests/Feature/FieldAgent/Team/ToggleTeamMemberActiveTest.php
git commit -m "feat(field-agent-teams): TeamMemberController@update toggles is_active"
```

---

## Task 12: Reject login for deactivated users

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`
- Test: extend `tests/Feature/FieldAgent/Team/ToggleTeamMemberActiveTest.php`

- [ ] **Step 1: Write the failing test (append to existing file)**

```php
    public function test_deactivated_user_cannot_log_in(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create([
            'is_active' => false,
            'password' => \Illuminate\Support\Facades\Hash::make('secret-pass'),
        ]);

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'secret-pass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_user_logs_in_normally(): void
    {
        $lead = User::factory()->lead()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('secret-pass'),
        ]);

        $this->post('/login', [
            'email' => $lead->email,
            'password' => 'secret-pass',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($lead);
    }
```

- [ ] **Step 2: Run to confirm `test_deactivated_user_cannot_log_in` fails**

Run: `php artisan test --compact --filter=ToggleTeamMemberActiveTest`
Expected: `test_deactivated_user_cannot_log_in` FAILS — login succeeds.

- [ ] **Step 3: Modify `authenticateUsing`**

In `app/Providers/FortifyServiceProvider.php` `configureActions()`, change the `authenticateUsing` block so that after a successful Hash check, an `is_active=false` user is rejected:

```php
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();

            if ($user && Hash::check((string) $request->input('password'), $user->password)) {
                if (! (bool) $user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => 'Your account has been deactivated. Contact your team lead.',
                    ]);
                }

                return $user;
            }

            $application = FieldAgentApplication::where('email', strtolower((string) $request->input('email')))
                ->whereNotNull('password')
                ->first();

            if ($application && Hash::check((string) $request->input('password'), $application->password)) {
                $message = match ($application->status->value) {
                    'pending', 'under_review' => 'Your field agent application is under review. We will notify you once approved.',
                    'rejected' => 'Your field agent application was not approved.'.($application->rejection_reason ? ' Reason: '.$application->rejection_reason : ''),
                    default => 'Invalid credentials.',
                };

                throw ValidationException::withMessages(['email' => $message]);
            }

            return null;
        });
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=ToggleTeamMemberActiveTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/FortifyServiceProvider.php tests/Feature/FieldAgent/Team/ToggleTeamMemberActiveTest.php
git commit -m "feat(field-agent-teams): reject login when user is_active=false"
```

---

## Task 13: EnforcePasswordChange middleware

**Files:**
- Create: `app/Http/Middleware/EnforcePasswordChange.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Http/Controllers/Settings/PasswordController.php`
- Test: `tests/Feature/FieldAgent/Team/ForcePasswordChangeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_with_must_change_password_redirected_from_dashboard(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)
            ->get('/field-agent/dashboard')
            ->assertRedirect('/settings/password');
    }

    public function test_member_can_reach_password_settings_to_change_it(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->get('/settings/password')->assertOk();
    }

    public function test_member_can_log_out_without_redirect_loop(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create();

        $this->actingAs($member)->post('/logout')->assertRedirect();
    }

    public function test_password_change_clears_must_change_password_flag(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create([
            'password' => Hash::make('+233551234567'),
        ]);

        $this->actingAs($member)->put('/settings/password', [
            'current_password' => '+233551234567',
            'password' => 'NewPass!9876',
            'password_confirmation' => 'NewPass!9876',
        ])->assertRedirect();

        $this->assertFalse((bool) $member->fresh()->must_change_password);
    }

    public function test_lead_without_flag_is_not_redirected(): void
    {
        $lead = User::factory()->lead()->create(['must_change_password' => false]);

        $this->actingAs($lead)->get('/field-agent/dashboard')->assertOk();
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=ForcePasswordChangeTest`
Expected: FAIL — middleware not yet active.

- [ ] **Step 3: Create the middleware**

`app/Http/Middleware/EnforcePasswordChange.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordChange
{
    /**
     * Path prefixes that a flagged user is still allowed to reach without
     * redirecting, so they can complete the password change or log out.
     *
     * @var list<string>
     */
    private const ALLOWED_PREFIXES = [
        'settings/password',
        'logout',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! (bool) $user->must_change_password) {
            return $next($request);
        }

        $path = $request->path();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        return redirect('/settings/password')
            ->with('error', 'You must change your default password before continuing.');
    }
}
```

- [ ] **Step 4: Register the middleware**

In `bootstrap/app.php`, add to the alias list:

```php
            'enforce-password-change' => \App\Http\Middleware\EnforcePasswordChange::class,
```

And append it to the `web` stack so all session-authed requests run it:

```php
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AuditLogMiddleware::class,
            \App\Http\Middleware\EnforcePasswordChange::class,
        ]);
```

- [ ] **Step 5: Clear flag on successful password update**

In `app/Http/Controllers/Settings/PasswordController.php`:

```php
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return back();
    }
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --compact --filter=ForcePasswordChangeTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/EnforcePasswordChange.php bootstrap/app.php app/Http/Controllers/Settings/PasswordController.php tests/Feature/FieldAgent/Team/ForcePasswordChangeTest.php
git commit -m "feat(field-agent-teams): EnforcePasswordChange middleware + clear flag on update"
```

---

## Task 14: Verify Inertia shared props expose team flags

The new fields are already serialized through `$user->toArray()` once Task 3 adds them to `$fillable` and `casts()`. This task is a verification gate to catch any future code that hides them.

**Files:**
- Test only: `tests/Feature/FieldAgent/Team/InertiaSharedPropsTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_lead_shared_props_carry_team_fields(): void
    {
        $lead = User::factory()->lead()->create();

        $this->actingAs($lead)->get('/field-agent/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_team_field_agent', true)
                ->where('auth.user.parent_user_id', null)
                ->where('auth.user.must_change_password', false)
                ->where('auth.user.is_active', true)
            );
    }

    public function test_member_shared_props_show_lead_id_and_password_flag(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)->get('/field-agent/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_team_field_agent', false)
                ->where('auth.user.parent_user_id', $lead->id)
                ->where('auth.user.must_change_password', false)
            );
    }
}
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --compact --filter=InertiaSharedPropsTest`
Expected: PASS — shared props auto-serialize via the model's toArray (Task 3 added the casts/fillable).

- [ ] **Step 3: If a field is missing, add an explicit override (only as a fallback)**

If any assertion fails, append to the `'auth.user'` shape in `app/Http/Middleware/HandleInertiaRequests.php`:

```php
            'auth' => [
                'user' => $user,
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
                // additional explicit team fields if toArray strips them:
                'team' => $user ? [
                    'is_lead' => $user->isLead(),
                    'is_team_member' => $user->isTeamMember(),
                ] : null,
            ],
```

(Skip this step if Step 2 passed.)

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/FieldAgent/Team/InertiaSharedPropsTest.php
git commit -m "test(field-agent-teams): verify team fields exposed on auth.user shared props"
```

---

## Task 15: Block members from earnings/payouts/targets/verification/terms

**Files:**
- Modify: `app/Http/Middleware/EnsureDashboardAccess.php`
- Test: `tests/Feature/FieldAgent/Team/MemberDashboardScopingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MemberDashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_forbidden_on_money_pages(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        foreach (['/field-agent/earnings', '/field-agent/payouts', '/field-agent/targets', '/field-agent/verification'] as $path) {
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    public function test_lead_can_access_money_pages(): void
    {
        $lead = User::factory()->lead()->create();

        foreach (['/field-agent/earnings', '/field-agent/payouts', '/field-agent/targets'] as $path) {
            $this->actingAs($lead)->get($path)->assertOk();
        }
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=MemberDashboardScopingTest`
Expected: FAIL — member receives 200 on money pages.

- [ ] **Step 3: Update `EnsureDashboardAccess`**

In `app/Http/Middleware/EnsureDashboardAccess.php`, after the existing `if ($user->role === 'field_agent' && ! str_starts_with($currentPath, 'field-agent')) { ... }` block, add a member-restriction block:

```php
        // Field-agent team members are barred from money/verification pages.
        if (
            $user->role === 'field_agent'
            && $user->parent_user_id !== null
            && (
                str_starts_with($currentPath, 'field-agent/earnings')
                || str_starts_with($currentPath, 'field-agent/payouts')
                || str_starts_with($currentPath, 'field-agent/targets')
                || str_starts_with($currentPath, 'field-agent/verification')
                || str_starts_with($currentPath, 'field-agent/terms')
            )
        ) {
            abort(403);
        }
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=MemberDashboardScopingTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/EnsureDashboardAccess.php tests/Feature/FieldAgent/Team/MemberDashboardScopingTest.php
git commit -m "feat(field-agent-teams): block members from earnings/payouts/targets/verification/terms"
```

---

## Task 16: Vendor visit start — allow members of lead, set onboarded_by_user_id

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/VendorVisitsController.php`
- Test: `tests/Feature/FieldAgent/Team/VendorAttributionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FieldAgent\Team;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VendorAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('session.driver', 'array');
        Config::set('cache.default', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_member_can_start_visit_under_leads_referral_code_and_attribution_set(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($member)
            ->post("/field-agent/visits/{$application->id}/start")
            ->assertRedirect();

        $this->assertSame($member->id, $application->fresh()->onboarded_by_user_id);
        $this->assertDatabaseHas('vendor_visits', [
            'vendor_application_id' => $application->id,
            'field_agent_user_id' => $member->id,
        ]);
    }

    public function test_member_of_other_lead_cannot_start_visit(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();
        $memberOfB = User::factory()->teamMember($leadB)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $codeA = ReferralCode::factory()->create(['influencer_id' => $leadA->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $codeA->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($memberOfB)
            ->post("/field-agent/visits/{$application->id}/start")
            ->assertForbidden();
    }

    public function test_first_claim_wins_onboarded_by_user_id_does_not_overwrite(): void
    {
        $lead = User::factory()->lead()->create();
        $memberA = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $memberB = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $code->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($memberA)->post("/field-agent/visits/{$application->id}/start");
        $this->actingAs($memberB)->post("/field-agent/visits/{$application->id}/start");

        $this->assertSame($memberA->id, $application->fresh()->onboarded_by_user_id);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=VendorAttributionTest`
Expected: FAIL — member rejected with 403 by `start()`'s current ownership check.

- [ ] **Step 3: Modify `start()`**

In `app/Http/Controllers/FieldAgent/VendorVisitsController.php`, replace `start()`:

```php
    public function start(Request $request, VendorApplication $application): RedirectResponse
    {
        $user = $request->user();
        $codeOwnerId = $application->referralCode?->influencer_id;
        $leadId = $user->parent_user_id ?? $user->id;
        abort_unless($codeOwnerId !== null && $codeOwnerId === $leadId, 403);

        $visit = VendorVisit::firstOrCreate(
            [
                'vendor_application_id' => $application->id,
                'field_agent_user_id' => $user->id,
            ],
            [
                'vendor_user_id' => $application->user_id,
                'status' => VendorVisitStatus::Draft->value,
                'started_at' => now(),
            ]
        );

        if ($application->onboarded_by_user_id === null) {
            $application->forceFill(['onboarded_by_user_id' => $user->id])->save();
        }

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }
```

- [ ] **Step 4: Add `onboarded_by_user_id` to `VendorApplication::$fillable`**

Open `app/Models/VendorApplication.php` and add `'onboarded_by_user_id'` to the `$fillable` array (or `$guarded = []`, depending on the model's convention). Verify by reading the model first.

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=VendorAttributionTest`
Expected: PASS.

- [ ] **Step 6: Run the existing visits test to confirm no regression**

Run: `php artisan test --compact --filter=FieldAgentOnboardingFlowTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FieldAgent/VendorVisitsController.php app/Models/VendorApplication.php tests/Feature/FieldAgent/Team/VendorAttributionTest.php
git commit -m "feat(field-agent-teams): allow members to claim lead's vendor visits and stamp onboarded_by_user_id"
```

---

## Task 17: Visits index — filter to member's own onboardings

**Files:**
- Modify: `app/Http/Controllers/FieldAgent/VendorVisitsController.php`
- Test: extend `tests/Feature/FieldAgent/Team/VendorAttributionTest.php`

- [ ] **Step 1: Write the failing test (append)**

```php
    public function test_member_visits_index_only_shows_own_onboardings(): void
    {
        $lead = User::factory()->lead()->create();
        $memberA = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $memberB = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor1 = User::factory()->vendor()->create();
        $vendor2 = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        $appA = VendorApplication::factory()->create([
            'user_id' => $vendor1->id,
            'referral_code_id' => $code->id,
            'onboarded_by_user_id' => $memberA->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);
        VendorApplication::factory()->create([
            'user_id' => $vendor2->id,
            'referral_code_id' => $code->id,
            'onboarded_by_user_id' => $memberB->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($memberA)
            ->get('/field-agent/visits')
            ->assertInertia(fn ($page) => $page
                ->component('field-agent/visits/index')
                ->has('applications', 1)
                ->where('applications.0.id', $appA->id)
            );
    }

    public function test_lead_visits_index_shows_all_referrals(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);
        $vendor1 = User::factory()->vendor()->create();
        $vendor2 = User::factory()->vendor()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $lead->id]);
        VendorApplication::factory()->create([
            'user_id' => $vendor1->id, 'referral_code_id' => $code->id,
            'onboarded_by_user_id' => $member->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);
        VendorApplication::factory()->create([
            'user_id' => $vendor2->id, 'referral_code_id' => $code->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->actingAs($lead)
            ->get('/field-agent/visits')
            ->assertInertia(fn ($page) => $page->has('applications', 2));
    }
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=VendorAttributionTest`
Expected: FAIL — member sees both apps.

- [ ] **Step 3: Modify `index()`**

```php
    public function index(Request $request): Response
    {
        $agent = $request->user();
        $leadId = $agent->parent_user_id ?? $agent->id;

        $query = VendorApplication::query()
            ->with(['user:id,business_name,name,email', 'vendorVisit'])
            ->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $leadId));

        if ($agent->parent_user_id !== null) {
            $query->where('onboarded_by_user_id', $agent->id);
        }

        return Inertia::render('field-agent/visits/index', [
            'applications' => $query->latest('id')->get(),
        ]);
    }
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=VendorAttributionTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FieldAgent/VendorVisitsController.php tests/Feature/FieldAgent/Team/VendorAttributionTest.php
git commit -m "feat(field-agent-teams): scope field-agent visits index to member's onboardings"
```

---

## Task 18: Field agent dashboard — member scoping

**Files:**
- Modify: `app/Http/Controllers/FieldAgentDashboardController.php`
- Test: extend `tests/Feature/FieldAgent/Team/MemberDashboardScopingTest.php`

- [ ] **Step 1: Write the failing test (append)**

```php
    public function test_member_dashboard_omits_money_fields_and_referral_code(): void
    {
        $lead = User::factory()->lead()->create();
        $member = User::factory()->teamMember($lead)->create(['must_change_password' => false]);

        $this->actingAs($member)
            ->get('/field-agent/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('field-agent/dashboard')
                ->where('referralCode', null)
                ->where('earningsSummary', null)
                ->has('vendorStats')
            );
    }
```

- [ ] **Step 2: Run to confirm it fails**

Run: `php artisan test --compact --filter=MemberDashboardScopingTest`
Expected: FAIL — `referralCode` is non-null because `getOrCreateReferralCode` runs for everyone.

- [ ] **Step 3: Branch dashboard rendering on membership**

In `app/Http/Controllers/FieldAgentDashboardController.php`:

```php
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $this->resolvePeriod($request);
        $isMember = $user->parent_user_id !== null;

        $referralCode = $isMember ? null : $this->getOrCreateReferralCode($user);
        $earningsSummary = $isMember ? null : $this->earningService->getUserEarningsSummary($user);
        $referralStats = $isMember ? ['total_earned' => 0] : app(\App\Services\ReferralService::class)->getInfluencerStats($user);

        return Inertia::render('field-agent/dashboard', [
            'agent' => [
                'id' => $user->id,
                'first_name' => $user->first_name ?? (explode(' ', (string) $user->name)[0] ?: $user->name),
                'referral_points' => $isMember ? 0 : (int) ($user->referral_points ?? 0),
                'earned_amount' => (float) ($referralStats['total_earned'] ?? 0),
            ],
            'period' => $period,
            'referralCode' => $referralCode ? ['code' => $referralCode->code] : null,
            'vendorStats' => $this->computeVendorStats($user, $period),
            'earningsSummary' => $earningsSummary,
            'activeTarget' => $isMember ? null : $this->computeActiveTarget($user),
            'recentVendors' => $this->computeRecentVendors($user),
        ]);
    }
```

- [ ] **Step 4: Update vendor-stats methods to scope by membership**

Replace `computeVendorStats` and `computeRecentVendors`:

```php
    private function computeVendorStats(User $agent, string $period): array
    {
        $now = CarbonImmutable::now();
        $start = match ($period) {
            'today' => $now->startOfDay(),
            'month' => $now->startOfMonth(),
            default => $now->startOfWeek(),
        };

        $base = VendorApplication::query();
        if ($agent->parent_user_id !== null) {
            $base->where('onboarded_by_user_id', $agent->id);
        } else {
            $base->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id));
        }

        $total = (clone $base)->count();
        $inPeriod = (clone $base)->where('created_at', '>=', $start);

        return [
            'total' => $total,
            'pending' => (clone $inPeriod)
                ->whereIn('status', [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])
                ->count(),
            'approved' => (clone $inPeriod)->where('status', VendorApplication::STATUS_APPROVED)->count(),
            'rejected' => (clone $inPeriod)->where('status', VendorApplication::STATUS_REJECTED)->count(),
        ];
    }

    private function computeRecentVendors(User $agent): array
    {
        $query = VendorApplication::query();
        if ($agent->parent_user_id !== null) {
            $query->where('onboarded_by_user_id', $agent->id);
        } else {
            $query->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id));
        }

        return $query
            ->with('user:id,name,business_name')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (VendorApplication $app) => [
                'id' => $app->id,
                'business_name' => $app->user?->business_name ?: ($app->user?->name ?? ''),
                'status' => $app->status,
                'created_at' => $app->created_at?->toIso8601String(),
            ])
            ->all();
    }
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=MemberDashboardScopingTest`
Expected: PASS.

- [ ] **Step 6: Run the existing dashboard test to confirm no regression**

Run: `php artisan test --compact --filter=FieldAgentDashboardTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FieldAgentDashboardController.php tests/Feature/FieldAgent/Team/MemberDashboardScopingTest.php
git commit -m "feat(field-agent-teams): scope field-agent dashboard for members (no money, own counts)"
```

---

## Task 19: Inertia page — team/index

**Files:**
- Create: `resources/js/pages/field-agent/team/index.tsx`

- [ ] **Step 1: Build the list page**

```tsx
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';

type Member = {
    id: number;
    name: string;
    email: string;
    phone: string;
    location: string | null;
    is_active: boolean;
    must_change_password: boolean;
    vendors_onboarded: number;
    created_at: string;
};

export default function TeamIndex({ members }: { members: Member[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Team', href: '/field-agent/team' }]}>
            <Head title="My Team" />

            <div className="flex items-center justify-between p-4">
                <h1 className="text-xl font-semibold">My Team</h1>
                <Button asChild>
                    <Link href="/field-agent/team/new">Add member</Link>
                </Button>
            </div>

            {members.length === 0 ? (
                <div className="rounded-md border border-dashed p-8 text-center text-muted-foreground mx-4">
                    No team members yet. Click <strong>Add member</strong> to create one.
                </div>
            ) : (
                <div className="divide-y rounded-md border mx-4">
                    {members.map((m) => (
                        <Link
                            key={m.id}
                            href={`/field-agent/team/${m.id}`}
                            className="flex items-center justify-between p-4 hover:bg-muted"
                        >
                            <div>
                                <div className="font-medium">{m.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {m.email} · {m.phone} · {m.location ?? '—'}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-sm">{m.vendors_onboarded} onboarded</span>
                                <Badge variant={m.is_active ? 'default' : 'secondary'}>
                                    {m.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify the index test still passes**

Run: `php artisan test --compact --filter=ListTeamMembersTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/field-agent/team/index.tsx
git commit -m "feat(field-agent-teams): team/index Inertia page"
```

---

## Task 20: Inertia page — team/new

**Files:**
- Create: `resources/js/pages/field-agent/team/new.tsx`

- [ ] **Step 1: Build the create form using Inertia v2 `<Form>` (project convention)**

```tsx
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, Link } from '@inertiajs/react';

export default function TeamNew() {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Team', href: '/field-agent/team' },
            { title: 'Add member', href: '/field-agent/team/new' },
        ]}>
            <Head title="Add team member" />

            <div className="max-w-xl mx-auto p-4 space-y-4">
                <h1 className="text-xl font-semibold">Add team member</h1>
                <p className="text-sm text-muted-foreground">
                    The member's default password will be their phone number. They will be required to change
                    it on first login.
                </p>

                <Form
                    action="/field-agent/team"
                    method="post"
                    resetOnSuccess
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" name="name" required />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" name="email" type="email" required />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div>
                                <Label htmlFor="phone">Phone (default password)</Label>
                                <Input id="phone" name="phone" placeholder="0551234567" required />
                                {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                            </div>

                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input id="location" name="location" required />
                                {errors.location && <p className="text-sm text-destructive">{errors.location}</p>}
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add member'}
                                </Button>
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/field-agent/team">Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify**

Run: `php artisan test --compact --filter=CreateTeamMemberTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/field-agent/team/new.tsx
git commit -m "feat(field-agent-teams): team/new Inertia page"
```

---

## Task 21: Inertia page — team/show

**Files:**
- Create: `resources/js/pages/field-agent/team/show.tsx`

- [ ] **Step 1: Build the detail page with deactivate toggle**

```tsx
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Form, Head } from '@inertiajs/react';

type Member = {
    id: number;
    name: string;
    email: string;
    phone: string;
    location: string | null;
    is_active: boolean;
    must_change_password: boolean;
    created_at: string;
};

type Vendor = {
    id: number;
    business_name: string;
    status: string;
    created_at: string | null;
};

export default function TeamShow({ member, vendors }: { member: Member; vendors: Vendor[] }) {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Team', href: '/field-agent/team' },
            { title: member.name, href: `/field-agent/team/${member.id}` },
        ]}>
            <Head title={member.name} />

            <div className="max-w-3xl mx-auto p-4 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">{member.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {member.email} · {member.phone} · {member.location ?? '—'}
                        </p>
                    </div>
                    <Badge variant={member.is_active ? 'default' : 'secondary'}>
                        {member.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </div>

                <Form
                    action={`/field-agent/team/${member.id}`}
                    method="patch"
                    transform={(data) => ({ ...data, is_active: !member.is_active })}
                >
                    {({ processing }) => (
                        <Button type="submit" variant={member.is_active ? 'destructive' : 'default'} disabled={processing}>
                            {member.is_active ? 'Deactivate member' : 'Reactivate member'}
                        </Button>
                    )}
                </Form>

                <section>
                    <h2 className="font-medium mb-2">Onboarded vendors ({vendors.length})</h2>
                    {vendors.length === 0 ? (
                        <p className="text-sm text-muted-foreground">None yet.</p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {vendors.map((v) => (
                                <div key={v.id} className="flex items-center justify-between p-3">
                                    <div>
                                        <div className="font-medium">{v.business_name}</div>
                                        <div className="text-xs text-muted-foreground">{v.created_at ?? ''}</div>
                                    </div>
                                    <Badge variant="outline">{v.status}</Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify**

Run: `php artisan test --compact --filter=ShowTeamMemberTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/field-agent/team/show.tsx
git commit -m "feat(field-agent-teams): team/show Inertia page"
```

---

## Task 22: Sidebar — show "Team" for leads, hide ReferralCodeCard for members

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/pages/field-agent/dashboard.tsx`

- [ ] **Step 1: Surface team flags from `auth.user` to the sidebar component**

In `resources/js/components/app-sidebar.tsx`, change the signature of `getNavItemsForRole` to also accept the lead/member booleans, then thread them in. Inside the `field_agent` block, add the "Team" entry conditionally:

```tsx
const getNavItemsForRole = (
    role: string,
    isLead: boolean,
    isMember: boolean,
): NavItem[] => {
    // ... existing admin/influencer blocks unchanged ...

    if (role === 'field_agent') {
        const items: NavItem[] = [
            { title: 'Dashboard', href: '/field-agent/dashboard', icon: LayoutGrid },
            { title: 'Vendor Onboarding', href: '/field-agent/visits', icon: UserCheck },
        ];

        if (!isMember) {
            items.push({
                title: 'Work & Earnings',
                icon: DollarSign,
                items: [
                    { title: 'My Targets', href: '/field-agent/targets', icon: Target },
                    { title: 'My Earnings', href: '/field-agent/earnings', icon: Wallet },
                    { title: 'Payouts', href: '/field-agent/payouts', icon: CheckCircle },
                ],
            });
            items.push({ title: 'My Verification', href: '/field-agent/verification', icon: ShieldCheck });
        }

        if (isLead) {
            items.push({ title: 'My Team', href: '/field-agent/team', icon: Users });
        }

        return items;
    }

    // ... rest unchanged ...
};
```

At the call site (line ~332):

```tsx
    const user = auth?.user;
    const isLead = !!user?.is_team_field_agent && user?.parent_user_id == null;
    const isMember = user?.parent_user_id != null;
    const navItems = getNavItemsForRole(user?.role || 'customer', isLead, isMember);
```

- [ ] **Step 2: Hide the referral code card on the field-agent dashboard for members**

In `resources/js/pages/field-agent/dashboard.tsx`, find the JSX that renders `<ReferralCodeCard>` and wrap it: only render when `referralCode != null`. (The server already passes `null` for members per Task 18.)

```tsx
{referralCode && <ReferralCodeCard code={referralCode.code} />}
```

- [ ] **Step 3: Smoke build**

Run: `pnpm run build` (or `pnpm typecheck` if available).
Expected: clean build.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/app-sidebar.tsx resources/js/pages/field-agent/dashboard.tsx
git commit -m "feat(field-agent-teams): sidebar Team entry + hide ReferralCodeCard for members"
```

---

## Task 23: Format and run full suite

**Files:** none (cleanup task)

- [ ] **Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: 0 issues, or auto-fixed.

- [ ] **Step 2: Run all field-agent team tests as a group**

Run: `php artisan test --compact tests/Feature/FieldAgent/Team`
Expected: all PASS.

- [ ] **Step 3: Run targeted regression checks**

Run each, expecting PASS:
```bash
php artisan test --compact --filter=FieldAgentDashboardTest
php artisan test --compact --filter=FieldAgentOnboardingFlowTest
php artisan test --compact --filter=FieldAgentLoginFlowTest
php artisan test --compact --filter=FieldAgentAccessRestrictionTest
php artisan test --compact --filter=SwitchFieldAgentTypeTest
```

- [ ] **Step 4: Ask the user about full suite**

Per project rule: ask the user whether to run the entire test suite (full suite is known to be slow/wedge locally per memory; CI is authoritative).

- [ ] **Step 5: Final commit (only if Pint or build produced changes)**

```bash
git add -A
git commit -m "style(field-agent-teams): pint + final cleanup"
```

---

## Done criteria

- All seven `tests/Feature/FieldAgent/Team/*Test.php` files pass.
- The five existing field-agent regression tests above still pass.
- Pint reports clean.
- A team-lead, logged in, can: list members, click "Add member", create one, click into them, see onboarded vendors, deactivate/reactivate.
- A new member, logged in with their phone as password, is forced to `/settings/password`, can change it, and lands on `/field-agent/dashboard` with no money tiles, no `ReferralCodeCard`, and a visits list scoped to their own onboardings.
- A member 403s on `/field-agent/{earnings,payouts,targets,verification,team,team/*}`.
- `referral_codes.influencer_id` and `earnings.user_id` continue to point at the lead for vendors a member onboards (no code change required — verified by `VendorAttributionTest`).
