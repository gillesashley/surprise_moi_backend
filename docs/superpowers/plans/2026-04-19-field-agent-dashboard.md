# Field Agent Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/field-agent/dashboard` so logged-in field agents see real vendor-pipeline counts, earnings, target progress, and their referral code — without introducing any new schema.

**Architecture:** Reuse the existing `referral_codes` + `vendor_applications.referral_code_id` infrastructure. `FieldAgentApprovalService::approve()` is updated to also create a `ReferralCode` for the new agent. A one-off artisan command backfills codes for existing approved agents. `FieldAgentDashboardController@index` is rewritten to aggregate vendor stats through the `referral_code_id` join and return them to Inertia. The React page is rewritten to match the new payload.

**Tech Stack:** Laravel 12 (PHP 8.2), Inertia.js v2 + React 19, shadcn/ui + MUI mix, PHPUnit v11, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-04-19-field-agent-dashboard-design.md`

**Branch:** `feat/field-agent-dashboard` (already exists, contains the approved spec).

---

## Prerequisites

- [ ] **Confirm branch and working tree are clean**

```bash
git branch --show-current
git status
```

Expected: on `feat/field-agent-dashboard`, working tree clean.

---

## File Structure

**Will modify:**
- `app/Services/FieldAgentApprovalService.php` — add referral-code creation inside the existing approval transaction.
- `app/Http/Controllers/FieldAgentDashboardController.php` — rewrite `index()` payload; add private helpers.
- `resources/js/Pages/field-agent/dashboard.tsx` — full rewrite of the page body to match the new payload.
- `tests/Feature/Admin/FieldAgentApplicationAdminTest.php` — add three tests for the new approval side-effect.

**Will create:**
- `app/Console/Commands/BackfillFieldAgentReferralCodesCommand.php` — one-shot backfill command.
- `tests/Feature/Console/BackfillFieldAgentReferralCodesCommandTest.php` — tests for the command.
- `tests/Feature/FieldAgentDashboardTest.php` — new test class for the rewritten controller.
- `resources/js/Pages/field-agent/components/ReferralCodeCard.tsx` — the code-display + copy-button component, colocated with the dashboard page.

Each task below ends with a Pint run and a commit.

---

## Task 1: Auto-generate ReferralCode on field-agent approval

**Files:**
- Modify: `app/Services/FieldAgentApprovalService.php:22-45`
- Test: `tests/Feature/Admin/FieldAgentApplicationAdminTest.php` (add tests at the end of the class)

- [ ] **Step 1: Add failing tests to the existing admin test class**

Open `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`. Add these three new tests to the class (at the end, before the closing `}`). Also add the `use App\Models\ReferralCode;` import at the top of the file if not already present.

```php
public function test_approval_creates_a_referral_code_for_the_new_user(): void
{
    // Mirror the setup from test_approval_creates_field_agent_user_and_clears_password() above.
    $application = FieldAgentApplication::factory()->create([
        'status' => 'pending',
        'password' => Hash::make('secret-password'),
    ]);

    app(\App\Services\FieldAgentApprovalService::class)->approve($application->fresh(), $this->admin);

    $newUser = User::where('email', $application->email)->firstOrFail();
    $code = ReferralCode::where('influencer_id', $newUser->id)->first();

    $this->assertNotNull($code, 'A ReferralCode should be created for the new agent');
}

public function test_generated_referral_code_uses_the_FA_prefix(): void
{
    // Mirror the setup from test_approval_creates_field_agent_user_and_clears_password() above.
    $application = FieldAgentApplication::factory()->create([
        'status' => 'pending',
        'password' => Hash::make('secret-password'),
    ]);

    app(\App\Services\FieldAgentApprovalService::class)->approve($application->fresh(), $this->admin);

    $newUser = User::where('email', $application->email)->firstOrFail();
    $code = ReferralCode::where('influencer_id', $newUser->id)->firstOrFail();

    $this->assertStringStartsWith('FA-', $code->code);
}

public function test_generated_referral_code_is_active_with_no_expiry_or_max_usages(): void
{
    // Mirror the setup from test_approval_creates_field_agent_user_and_clears_password() above.
    $application = FieldAgentApplication::factory()->create([
        'status' => 'pending',
        'password' => Hash::make('secret-password'),
    ]);

    app(\App\Services\FieldAgentApprovalService::class)->approve($application->fresh(), $this->admin);

    $newUser = User::where('email', $application->email)->firstOrFail();
    $code = ReferralCode::where('influencer_id', $newUser->id)->firstOrFail();

    $this->assertTrue($code->is_active);
    $this->assertNull($code->expires_at);
    $this->assertNull($code->max_usages);
}
```

Before adding these tests, open the existing `test_approval_creates_field_agent_user_and_clears_password()` method in the same file and mirror its exact setup (factory chain, status transitions, any helper calls) for building the application into a reviewable state — `FieldAgentApprovalService::approve()` will throw if `canBeReviewed()` returns false. Do not guess factory states or enum case names; copy the working pattern verbatim and swap in only the assertion body shown above. If the existing test uses a string status like `'under_review'` instead of an enum, match that.

- [ ] **Step 2: Run the new tests and verify they fail**

```bash
php artisan test --compact --filter='test_approval_creates_a_referral_code_for_the_new_user|test_generated_referral_code_uses_the_FA_prefix|test_generated_referral_code_is_active_with_no_expiry_or_max_usages'
```

Expected: three failing tests. Each should fail with `null` returned by `ReferralCode::where(...)->first()` (or similar).

- [ ] **Step 3: Modify `FieldAgentApprovalService::approve()` to also create a ReferralCode**

Open `app/Services/FieldAgentApprovalService.php`. Add `use App\Models\ReferralCode;` to the imports. Inside the `approve()` method's `DB::transaction` closure, after the `$application->update(...)` call and before the `Notification::send(...)` call, insert:

```php
$code = new ReferralCode([
    'influencer_id' => $user->id,
    'is_active' => true,
]);
$code->prefix = ReferralCode::getPrefixForRole('field_agent');
$code->save();
```

The `creating` boot hook on `ReferralCode` auto-generates the `code` string using the `FA-` prefix. No other argument is needed — `expires_at` and `max_usages` default to `null`.

- [ ] **Step 4: Run the new tests and verify they pass**

```bash
php artisan test --compact --filter='test_approval_creates_a_referral_code_for_the_new_user|test_generated_referral_code_uses_the_FA_prefix|test_generated_referral_code_is_active_with_no_expiry_or_max_usages'
```

Expected: all three pass.

- [ ] **Step 5: Run the full FieldAgentApplicationAdminTest to confirm no regressions**

```bash
php artisan test --compact --filter=FieldAgentApplicationAdminTest
```

Expected: all tests in the class pass, including existing ones.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/FieldAgentApprovalService.php tests/Feature/Admin/FieldAgentApplicationAdminTest.php
git commit -m "$(cat <<'EOF'
feat(field-agent): generate referral code on application approval

Adds ReferralCode creation to FieldAgentApprovalService::approve() so
every newly-approved field agent has a FA-prefixed code. The code is
the attribution link used by the dashboard to count the agent's
onboarded vendors.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Backfill command for existing approved field agents

**Files:**
- Create: `app/Console/Commands/BackfillFieldAgentReferralCodesCommand.php`
- Test: `tests/Feature/Console/BackfillFieldAgentReferralCodesCommandTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Console/BackfillFieldAgentReferralCodesCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillFieldAgentReferralCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_codes_for_agents_missing_one(): void
    {
        $agentA = User::factory()->create(['role' => 'field_agent']);
        $agentB = User::factory()->create(['role' => 'field_agent']);
        $agentC = User::factory()->create(['role' => 'field_agent']);
        $existingCode = new ReferralCode(['influencer_id' => $agentC->id, 'is_active' => true]);
        $existingCode->prefix = ReferralCode::getPrefixForRole('field_agent');
        $existingCode->save();

        $this->artisan('field-agents:backfill-referral-codes')
            ->assertSuccessful();

        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentA->id]);
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentB->id]);
        $this->assertSame(3, ReferralCode::count());
    }

    public function test_it_skips_non_field_agent_users(): void
    {
        User::factory()->create(['role' => 'customer']);
        User::factory()->create(['role' => 'vendor']);
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')
            ->assertSuccessful();

        $this->assertSame(1, ReferralCode::count());
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agent->id]);
    }

    public function test_it_is_idempotent(): void
    {
        User::factory()->create(['role' => 'field_agent']);
        User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();
        $firstCount = ReferralCode::count();

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();
        $secondCount = ReferralCode::count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_it_generates_fa_prefixed_codes(): void
    {
        User::factory()->create(['role' => 'field_agent']);

        $this->artisan('field-agents:backfill-referral-codes')->assertSuccessful();

        $this->assertStringStartsWith('FA-', ReferralCode::first()->code);
    }
}
```

- [ ] **Step 2: Run the tests and verify they fail**

```bash
php artisan test --compact --filter=BackfillFieldAgentReferralCodesCommandTest
```

Expected: all four fail with "command not found" or similar.

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/BackfillFieldAgentReferralCodesCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillFieldAgentReferralCodesCommand extends Command
{
    protected $signature = 'field-agents:backfill-referral-codes';

    protected $description = 'Create a referral code for every field agent that does not already have one.';

    public function handle(): int
    {
        $agentsWithoutCode = User::query()
            ->where('role', 'field_agent')
            ->whereDoesntHave('referralCodes')
            ->get();

        if ($agentsWithoutCode->isEmpty()) {
            $this->info('All field agents already have referral codes.');

            return self::SUCCESS;
        }

        $this->info("Creating referral codes for {$agentsWithoutCode->count()} agent(s)...");

        foreach ($agentsWithoutCode as $agent) {
            $code = new ReferralCode([
                'influencer_id' => $agent->id,
                'is_active' => true,
            ]);
            $code->prefix = ReferralCode::getPrefixForRole('field_agent');
            $code->save();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Verify `User::referralCodes()` relationship exists; if not, add it**

Check `app/Models/User.php` for a `referralCodes()` HasMany relationship. Run:

```bash
php artisan tinker --execute="echo method_exists(\App\Models\User::class, 'referralCodes') ? 'yes' : 'no';"
```

If it prints `no`, open `app/Models/User.php` and add this method in the relationships section:

```php
public function referralCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ReferralCode::class, 'influencer_id');
}
```

Also add `use App\Models\ReferralCode;` at the top of `User.php` if not present. If the relationship already exists, no change is needed.

- [ ] **Step 5: Run the command tests and verify they pass**

```bash
php artisan test --compact --filter=BackfillFieldAgentReferralCodesCommandTest
```

Expected: all four pass.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/BackfillFieldAgentReferralCodesCommand.php \
        app/Models/User.php \
        tests/Feature/Console/BackfillFieldAgentReferralCodesCommandTest.php
git commit -m "$(cat <<'EOF'
feat(field-agent): add backfill command for agent referral codes

Introduces the field-agents:backfill-referral-codes artisan command
so existing approved field agents get the referral code that the
approval service now creates automatically. Idempotent; safe to
re-run.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Dashboard controller — vendor stats, referral code, recent vendors

**Files:**
- Modify: `app/Http/Controllers/FieldAgentDashboardController.php:14-47`
- Test: `tests/Feature/FieldAgentDashboardTest.php` (new)

### 3a — Test scaffold + referral code in payload

- [ ] **Step 1: Write the failing tests for the referral-code part of the payload**

Create `tests/Feature/FieldAgentDashboardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'field_agent']);
        $code = new ReferralCode(['influencer_id' => $this->agent->id, 'is_active' => true]);
        $code->prefix = ReferralCode::getPrefixForRole('field_agent');
        $code->save();
    }

    public function test_payload_includes_the_agents_referral_code(): void
    {
        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/dashboard')
            ->has('referralCode', fn ($code) => $code
                ->where('code', fn ($c) => str_starts_with($c, 'FA-'))
                ->etc()
            )
        );
    }

    public function test_payload_lazily_creates_a_referral_code_when_agent_has_none(): void
    {
        $agentWithoutCode = User::factory()->create(['role' => 'field_agent']);

        $response = $this->actingAs($agentWithoutCode)->get('/field-agent/dashboard');

        $response->assertOk();
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentWithoutCode->id]);
    }
}
```

- [ ] **Step 2: Run these tests and verify they fail**

```bash
php artisan test --compact --filter=FieldAgentDashboardTest
```

Expected: both fail. First fails because `referralCode` prop does not exist. Second may fail or may pass depending on current page state; the essential assertion is the missing DB row.

- [ ] **Step 3: Add referral-code logic to the controller**

Open `app/Http/Controllers/FieldAgentDashboardController.php`. Add these imports at the top:

```php
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Carbon\CarbonImmutable;
```

Add a new private helper method at the bottom of the class:

```php
private function getOrCreateReferralCode(User $agent): ReferralCode
{
    $code = ReferralCode::where('influencer_id', $agent->id)->first();

    if ($code) {
        return $code;
    }

    $code = new ReferralCode(['influencer_id' => $agent->id, 'is_active' => true]);
    $code->prefix = ReferralCode::getPrefixForRole('field_agent');
    $code->save();

    return $code;
}
```

Now modify `index()` to include the `referralCode` key. Replace the existing `return Inertia::render(...)` block with the shape below. Leave the existing `$targetStats`, `$earningsSummary`, `$activeTargets`, and `$recentEarnings` computations — they will be removed incrementally in later sub-steps:

```php
$referralCode = $this->getOrCreateReferralCode($user);

return Inertia::render('field-agent/dashboard', [
    'stats' => array_merge($targetStats ?? [], $earningsSummary ?? []),
    'activeTargets' => $activeTargets ?? [],
    'recentEarnings' => $recentEarnings ?? [],
    'referralCode' => [
        'code' => $referralCode->code,
    ],
]);
```

- [ ] **Step 4: Run the tests and verify they pass**

```bash
php artisan test --compact --filter=FieldAgentDashboardTest
```

Expected: both tests in the file pass.

### 3b — Vendor stats (total / pending / approved / rejected)

- [ ] **Step 5: Add failing tests for vendor stats**

Append these methods to `tests/Feature/FieldAgentDashboardTest.php` (inside the class, before the closing `}`):

```php
public function test_stats_include_only_vendors_who_used_this_agents_referral_code(): void
{
    $otherAgent = User::factory()->create(['role' => 'field_agent']);
    $otherCode = new ReferralCode(['influencer_id' => $otherAgent->id, 'is_active' => true]);
    $otherCode->prefix = ReferralCode::getPrefixForRole('field_agent');
    $otherCode->save();

    $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

    VendorApplication::factory()->approved()->create(['referral_code_id' => $myCode->id]);
    VendorApplication::factory()->approved()->create(['referral_code_id' => $myCode->id]);
    VendorApplication::factory()->approved()->create(['referral_code_id' => $otherCode->id]);
    VendorApplication::factory()->approved()->create(['referral_code_id' => null]);

    $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('vendorStats.total', 2)
        ->where('vendorStats.approved', 2)
        ->etc()
    );
}

public function test_total_vendors_ignores_period_filter(): void
{
    $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

    VendorApplication::factory()->approved()->create([
        'referral_code_id' => $myCode->id,
        'created_at' => Carbon::now()->subYear(),
    ]);

    $week = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=week');
    $month = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=month');

    $week->assertInertia(fn ($page) => $page->where('vendorStats.total', 1)->etc());
    $month->assertInertia(fn ($page) => $page->where('vendorStats.total', 1)->etc());
}

public function test_period_filter_scopes_pending_approved_rejected_counts(): void
{
    $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

    VendorApplication::factory()->pending()->create([
        'referral_code_id' => $myCode->id,
        'created_at' => Carbon::now()->startOfDay()->addHour(),
    ]);
    VendorApplication::factory()->approved()->create([
        'referral_code_id' => $myCode->id,
        'created_at' => Carbon::now()->subDays(2),
    ]);
    VendorApplication::factory()->rejected()->create([
        'referral_code_id' => $myCode->id,
        'created_at' => Carbon::now()->subDays(20),
    ]);

    $today = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=today');
    $today->assertInertia(fn ($page) => $page
        ->where('vendorStats.pending', 1)
        ->where('vendorStats.approved', 0)
        ->where('vendorStats.rejected', 0)
        ->etc()
    );

    $week = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=week');
    $week->assertInertia(fn ($page) => $page
        ->where('vendorStats.pending', 1)
        ->where('vendorStats.approved', 1)
        ->where('vendorStats.rejected', 0)
        ->etc()
    );

    $month = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=month');
    $month->assertInertia(fn ($page) => $page
        ->where('vendorStats.pending', 1)
        ->where('vendorStats.approved', 1)
        ->where('vendorStats.rejected', 1)
        ->etc()
    );
}

public function test_invalid_period_falls_back_to_week(): void
{
    $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();
    VendorApplication::factory()->approved()->create([
        'referral_code_id' => $myCode->id,
        'created_at' => Carbon::now()->subDays(2),
    ]);

    $response = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=garbage');

    $response->assertInertia(fn ($page) => $page
        ->where('period', 'week')
        ->where('vendorStats.approved', 1)
        ->etc()
    );
}
```

- [ ] **Step 6: Run the four new tests and verify they fail**

```bash
php artisan test --compact --filter='FieldAgentDashboardTest::test_stats_include_only_vendors_who_used_this_agents_referral_code|FieldAgentDashboardTest::test_total_vendors_ignores_period_filter|FieldAgentDashboardTest::test_period_filter_scopes_pending_approved_rejected_counts|FieldAgentDashboardTest::test_invalid_period_falls_back_to_week'
```

Expected: all four fail — `vendorStats` prop doesn't exist yet.

- [ ] **Step 7: Add vendor stats + period handling to the controller**

In `app/Http/Controllers/FieldAgentDashboardController.php`, add these two private helper methods at the bottom of the class:

```php
private function resolvePeriod(Request $request): string
{
    $raw = (string) $request->input('period', 'week');

    return in_array($raw, ['today', 'week', 'month'], true) ? $raw : 'week';
}

/**
 * @return array{total:int, pending:int, approved:int, rejected:int}
 */
private function computeVendorStats(User $agent, string $period): array
{
    $now = CarbonImmutable::now();
    $start = match ($period) {
        'today' => $now->startOfDay(),
        'month' => $now->startOfMonth(),
        default => $now->startOfWeek(),
    };

    $base = VendorApplication::query()
        ->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id));

    $total = (clone $base)->count();

    $inPeriod = (clone $base)->where('created_at', '>=', $start);

    return [
        'total' => $total,
        'pending' => (clone $inPeriod)
            ->whereIn('status', [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])
            ->count(),
        'approved' => (clone $inPeriod)
            ->where('status', VendorApplication::STATUS_APPROVED)
            ->count(),
        'rejected' => (clone $inPeriod)
            ->where('status', VendorApplication::STATUS_REJECTED)
            ->count(),
    ];
}
```

Now update `index()` to use them. Replace the current body of `index()` with:

```php
public function index(Request $request): Response
{
    $user = $request->user();
    $period = $this->resolvePeriod($request);
    $referralCode = $this->getOrCreateReferralCode($user);

    $targetStats = $this->targetService->getUserTargetStats($user);
    $earningsSummary = $this->earningService->getUserEarningsSummary($user);

    $activeTargets = Target::where('user_id', $user->id)
        ->where('status', Target::STATUS_ACTIVE)
        ->with(['assignedBy'])
        ->latest()
        ->get();

    $recentEarnings = Earning::where('user_id', $user->id)
        ->latest('earned_at')
        ->limit(5)
        ->get();

    return Inertia::render('field-agent/dashboard', [
        'agent' => [
            'id' => $user->id,
            'first_name' => $user->first_name ?? explode(' ', (string) $user->name)[0] ?? $user->name,
        ],
        'period' => $period,
        'referralCode' => [
            'code' => $referralCode->code,
        ],
        'vendorStats' => $this->computeVendorStats($user, $period),
        'stats' => array_merge($targetStats ?? [], $earningsSummary ?? []),
        'activeTargets' => $activeTargets,
        'recentEarnings' => $recentEarnings,
    ]);
}
```

Also add a `referralCode()` relationship on `VendorApplication` if one doesn't already exist. Check by running:

```bash
php artisan tinker --execute="echo method_exists(\App\Models\VendorApplication::class, 'referralCode') ? 'yes' : 'no';"
```

If it prints `yes` (the spec exploration found a `referralCode(): BelongsTo` already on line 114), no change needed. If `no`, add to `app/Models/VendorApplication.php`:

```php
public function referralCode(): BelongsTo
{
    return $this->belongsTo(ReferralCode::class);
}
```

- [ ] **Step 8: Run the four tests and verify they pass**

```bash
php artisan test --compact --filter='FieldAgentDashboardTest::test_stats_include_only_vendors_who_used_this_agents_referral_code|FieldAgentDashboardTest::test_total_vendors_ignores_period_filter|FieldAgentDashboardTest::test_period_filter_scopes_pending_approved_rejected_counts|FieldAgentDashboardTest::test_invalid_period_falls_back_to_week'
```

Expected: all four pass.

### 3c — Recent vendors list + active-target card

- [ ] **Step 9: Write failing tests for recent vendors and active-target null case**

Append to `tests/Feature/FieldAgentDashboardTest.php`:

```php
public function test_recent_vendors_returns_last_five_in_reverse_chronological_order(): void
{
    $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

    $ids = [];
    foreach (range(1, 7) as $i) {
        $app = VendorApplication::factory()->pending()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->subMinutes($i),
        ]);
        $ids[] = $app->id;
    }

    $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->has('recentVendors', 5)
        ->where('recentVendors.0.id', $ids[0])
        ->where('recentVendors.4.id', $ids[4])
        ->etc()
    );
}

public function test_active_target_card_omitted_when_no_active_target(): void
{
    $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('activeTarget', null)
        ->etc()
    );
}
```

- [ ] **Step 10: Run and verify they fail**

```bash
php artisan test --compact --filter='FieldAgentDashboardTest::test_recent_vendors_returns_last_five_in_reverse_chronological_order|FieldAgentDashboardTest::test_active_target_card_omitted_when_no_active_target'
```

Expected: both fail — `recentVendors` and `activeTarget` props don't exist.

- [ ] **Step 11: Add recent vendors and a single active-target projection to the controller**

In `FieldAgentDashboardController.php`, add a final private helper:

```php
/**
 * @return array{id:int, business_name:string, status:string, created_at:string}[]
 */
private function computeRecentVendors(User $agent): array
{
    return VendorApplication::query()
        ->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id))
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

private function computeActiveTarget(User $agent): ?array
{
    $target = Target::where('user_id', $agent->id)
        ->where('status', Target::STATUS_ACTIVE)
        ->latest()
        ->first();

    if (! $target) {
        return null;
    }

    return [
        'id' => $target->id,
        'current' => (float) $target->current_value,
        'goal' => (float) $target->target_value,
        'completion_percentage' => $target->getCompletionPercentage(),
        'ends_at' => $target->end_date?->toIso8601String(),
    ];
}
```

Now update the `Inertia::render` call in `index()` — add two new keys and remove `activeTargets` (superseded by `activeTarget`) and `stats`/`recentEarnings` (superseded by the explicit shape below):

```php
return Inertia::render('field-agent/dashboard', [
    'agent' => [
        'id' => $user->id,
        'first_name' => $user->first_name ?? explode(' ', (string) $user->name)[0] ?? $user->name,
    ],
    'period' => $period,
    'referralCode' => [
        'code' => $referralCode->code,
    ],
    'vendorStats' => $this->computeVendorStats($user, $period),
    'earningsSummary' => $earningsSummary,
    'activeTarget' => $this->computeActiveTarget($user),
    'recentVendors' => $this->computeRecentVendors($user),
]);
```

Delete the now-unused local variables `$targetStats`, `$activeTargets`, `$recentEarnings` and their corresponding queries from `index()`. The `$earningsSummary` is still used. Remove the `use App\Models\Earning;` and `use App\Models\Target;` imports ONLY if they're no longer referenced (run `grep -n "Earning\|Target" app/Http/Controllers/FieldAgentDashboardController.php` to confirm).

Actually keep `Target` — the controller's `targets()` method still uses it. And keep `Earning` — `earnings()` still uses it. Only the unused locals in `index()` are removed.

- [ ] **Step 12: Run all FieldAgentDashboardTest tests and verify they pass**

```bash
php artisan test --compact --filter=FieldAgentDashboardTest
```

Expected: all tests pass.

- [ ] **Step 13: Pint + commit Task 3**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/FieldAgentDashboardController.php \
        app/Models/VendorApplication.php \
        tests/Feature/FieldAgentDashboardTest.php
git commit -m "$(cat <<'EOF'
feat(field-agent): rebuild dashboard controller payload

index() now returns vendorStats (total/pending/approved/rejected,
period-scoped), recentVendors (last 5), activeTarget, referralCode,
and earningsSummary in a shape built for the new UI. Period filter
accepts today|week|month; invalid values fall back to week.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Dashboard frontend rewrite

**Files:**
- Create: `resources/js/Pages/field-agent/components/ReferralCodeCard.tsx`
- Modify (full rewrite): `resources/js/Pages/field-agent/dashboard.tsx`

No unit tests for React in this project — verification is the existing PHP feature tests (payload shape) plus a manual browser smoke test.

- [ ] **Step 1: Create the ReferralCodeCard component**

Create `resources/js/Pages/field-agent/components/ReferralCodeCard.tsx`:

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface Props {
    code: string;
}

export default function ReferralCodeCard({ code }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Silent fail — non-HTTPS or denied clipboard permission
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your Referral Code</CardTitle>
            </CardHeader>
            <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    <Typography
                        variant="h5"
                        sx={{ fontFamily: 'monospace', fontWeight: 700, letterSpacing: '0.05em' }}
                    >
                        {code}
                    </Typography>
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                        aria-label="Copy referral code"
                    >
                        {copied ? <Check size={16} /> : <Copy size={16} />}
                        {copied ? 'Copied' : 'Copy'}
                    </button>
                </Box>
                <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    Share this code with a vendor at registration so they are attributed to you.
                </Typography>
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 2: Rewrite the dashboard page**

Replace the entire contents of `resources/js/Pages/field-agent/dashboard.tsx` with:

```tsx
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { CheckCircle, Clock, DollarSign, TrendingUp, Users, XCircle } from 'lucide-react';
import ReferralCodeCard from './components/ReferralCodeCard';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/field-agent/dashboard' },
];

type Period = 'today' | 'week' | 'month';

interface VendorStats {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
}

interface EarningsSummary {
    total_earnings: number;
    pending_earnings: number;
    approved_earnings: number;
    paid_earnings: number;
}

interface ActiveTarget {
    id: number;
    current: number;
    goal: number;
    completion_percentage: number;
    ends_at: string | null;
}

interface RecentVendor {
    id: number;
    business_name: string;
    status: string;
    created_at: string;
}

interface DashboardProps {
    agent: { id: number; first_name: string };
    period: Period;
    referralCode: { code: string };
    vendorStats: VendorStats;
    earningsSummary: EarningsSummary;
    activeTarget: ActiveTarget | null;
    recentVendors: RecentVendor[];
}

function greeting(): string {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
}

function StatTile({
    title,
    value,
    icon: Icon,
    iconBg,
}: {
    title: string;
    value: number | string;
    icon: React.ElementType;
    iconBg: string;
}) {
    return (
        <Box
            sx={{
                borderRadius: 3,
                p: 3,
                boxShadow: 1,
                bgcolor: 'background.paper',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
            }}
        >
            <Box>
                <Typography variant="body2" fontWeight={500} color="text.secondary">
                    {title}
                </Typography>
                <Typography variant="h4" fontWeight={700} sx={{ letterSpacing: '-0.02em' }}>
                    {value}
                </Typography>
            </Box>
            <Box sx={{ borderRadius: 2, p: 1.5, bgcolor: iconBg }}>
                <Icon style={{ width: 22, height: 22, color: 'white' }} />
            </Box>
        </Box>
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'approved') return 'default';
    if (status === 'rejected') return 'destructive';
    return 'secondary';
}

export default function FieldAgentDashboard({
    agent,
    period,
    referralCode,
    vendorStats,
    earningsSummary,
    activeTarget,
    recentVendors,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const displayName = agent?.first_name || auth.user?.name;

    const changePeriod = (next: Period) => {
        router.visit('/field-agent/dashboard', {
            data: { period: next },
            only: ['period', 'vendorStats', 'recentVendors'],
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Field Agent Dashboard" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                {/* Header */}
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: { xs: 'column', md: 'row' },
                        alignItems: { md: 'center' },
                        justifyContent: 'space-between',
                        gap: 2,
                    }}
                >
                    <Box>
                        <Typography variant="h5" fontWeight={700}>
                            {greeting()}, {displayName}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Here's how your onboarding is tracking.
                        </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        {(['today', 'week', 'month'] as Period[]).map((p) => (
                            <button
                                key={p}
                                type="button"
                                onClick={() => changePeriod(p)}
                                className={`rounded-md border px-3 py-1.5 text-sm capitalize ${
                                    period === p ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'
                                }`}
                            >
                                {p === 'today' ? 'Today' : p === 'week' ? 'This Week' : 'This Month'}
                            </button>
                        ))}
                    </Box>
                </Box>

                {/* Referral code */}
                <ReferralCodeCard code={referralCode.code} />

                {/* Row 1: Vendor pipeline */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' },
                    }}
                >
                    <StatTile title="Total Vendors" value={vendorStats.total} icon={Users} iconBg="#3b82f6" />
                    <StatTile title="Pending" value={vendorStats.pending} icon={Clock} iconBg="#f59e0b" />
                    <StatTile title="Approved" value={vendorStats.approved} icon={CheckCircle} iconBg="#22c55e" />
                    <StatTile title="Rejected" value={vendorStats.rejected} icon={XCircle} iconBg="#ef4444" />
                </Box>

                {/* Row 2: Earnings + Target */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: { xs: '1fr', md: activeTarget ? 'repeat(2, 1fr)' : '1fr' },
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Earnings</CardTitle>
                            <CardDescription>Your commission balance</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', gap: 3, flexWrap: 'wrap', mb: 2 }}>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Total</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.total_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Pending</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.pending_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Available</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.approved_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                            </Box>
                            <a
                                href="/field-agent/payouts"
                                className="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                            >
                                <DollarSign size={16} />
                                Request payout
                            </a>
                        </CardContent>
                    </Card>

                    {activeTarget && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Target Progress</CardTitle>
                                <CardDescription>
                                    {activeTarget.current} / {activeTarget.goal}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ height: 10, width: '100%', borderRadius: 5, bgcolor: 'action.hover', mb: 1 }}>
                                    <Box
                                        sx={{
                                            height: 10,
                                            borderRadius: 5,
                                            bgcolor: 'primary.main',
                                            width: `${Math.min(100, activeTarget.completion_percentage)}%`,
                                        }}
                                    />
                                </Box>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        {activeTarget.completion_percentage}% complete
                                    </Typography>
                                    {activeTarget.ends_at && (
                                        <Typography variant="body2" color="text.secondary">
                                            Ends {new Date(activeTarget.ends_at).toLocaleDateString()}
                                        </Typography>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    )}
                </Box>

                {/* Row 3: Recent vendors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent vendors</CardTitle>
                        <CardDescription>Last 5 vendors attributed to you</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentVendors.length === 0 ? (
                            <Typography variant="body2" color="text.secondary">
                                No vendors yet. Share your referral code with one to get started.
                            </Typography>
                        ) : (
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                {recentVendors.map((v) => (
                                    <Box
                                        key={v.id}
                                        sx={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            borderRadius: 2,
                                            border: 1,
                                            borderColor: 'divider',
                                            p: 2,
                                        }}
                                    >
                                        <Box>
                                            <Typography fontWeight={500}>
                                                {v.business_name || 'Unnamed vendor'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                {new Date(v.created_at).toLocaleDateString()}
                                            </Typography>
                                        </Box>
                                        <Badge variant={statusVariant(v.status)}>
                                            {v.status.replace('_', ' ')}
                                        </Badge>
                                    </Box>
                                ))}
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
```

Note: this removes the old `TrendingUp` icon from the imports list if it isn't used; keep only the icons that are referenced. Verify by reading the final imports line after writing.

- [ ] **Step 3: Run the full feature-test suite for field-agent code**

```bash
php artisan test --compact --filter='FieldAgent|BackfillFieldAgentReferralCodes'
```

Expected: all tests pass. Fix any regressions before proceeding.

- [ ] **Step 4: Smoke test in the browser**

Start/ensure the dev environment is running (`composer run dev` or per project convention), then log in as an approved field agent and navigate to `/field-agent/dashboard`. Confirm:
- Greeting + period toggle render in the header.
- A `FA-` referral code shows with a working `Copy` button.
- Four KPI tiles render with the correct numbers.
- Earnings card renders; `Request payout` links to `/field-agent/payouts`.
- Target card renders only if the agent has an active target.
- Recent vendors list renders; empty-state shows when no vendors exist.
- Clicking the period toggle updates the pipeline tiles without a full page reload.

If no field-agent user with vendor data exists locally, seed some first. From tinker:

```bash
php artisan tinker
```

```php
$agent = \App\Models\User::where('role', 'field_agent')->first();
$code = \App\Models\ReferralCode::where('influencer_id', $agent->id)->first();
\App\Models\VendorApplication::factory()->approved()->create(['referral_code_id' => $code->id]);
\App\Models\VendorApplication::factory()->pending()->create(['referral_code_id' => $code->id]);
```

- [ ] **Step 5: Commit Task 4**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Pages/field-agent/dashboard.tsx \
        resources/js/Pages/field-agent/components/ReferralCodeCard.tsx
git commit -m "$(cat <<'EOF'
feat(field-agent): rebuild dashboard UI with referral code + vendor pipeline

Replaces the old targets-and-earnings layout with: header + period
toggle, referral-code card (with copy), vendor-pipeline KPI tiles,
earnings + target cards, and a recent-vendors list. Partial-reload
period switching via Inertia v2.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Final verification

- [ ] **Step 1: Run the full test suite once more**

```bash
php artisan test --compact
```

Expected: no regressions anywhere in the project. If any unrelated test fails, investigate — do not skip.

- [ ] **Step 2: Confirm the branch is shippable**

```bash
git log --oneline main..HEAD
git status
```

Expected: a clean working tree and a concise sequence of task commits on `feat/field-agent-dashboard`.

- [ ] **Step 3: Run the backfill on any existing environments**

For local/staging/prod, one-time after deploy:

```bash
php artisan field-agents:backfill-referral-codes
```

This is the last step; the command is idempotent, so a second run anywhere is a no-op.
