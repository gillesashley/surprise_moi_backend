# Admin Notifications Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a system-wide read-only Notifications page for admins/super-admins that lists vendor onboarding, tier upgrade, and field-agent application events derived from existing domain records.

**Architecture:** Approach A from the design spec — `AdminNotificationFeedService` queries existing models, maps each row into a unified shape, merges + sorts desc by `occurred_at`, paginates. `AdminNotificationFeedController` renders an Inertia page. Frontend lives at `resources/js/pages/notifications/index.tsx`. New sidebar entry "Notifications" in `app-sidebar.tsx`.

**Tech Stack:** Laravel 12, Inertia v2 (React 19), PHPUnit 11, PostgreSQL, Pint. Docker-only — every shell command runs through `docker compose exec -T app ...`.

**Spec:** `docs/superpowers/specs/2026-05-05-admin-notifications-page-design.md`

**Branch:** `feat/admin-notifications-page` (already exists, off `main`).

**Test env note:** Local env quirk — `php artisan test` and bare `vendor/bin/phpunit` both ignore the env block in `phpunit.xml` and read `.env` (APP_ENV=local), causing `RefreshDatabase` to fight the dev DB. Always run tests with explicit env: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit ...`.

---

## File Structure

**New files (PHP):**
- `app/Services/AdminNotificationFeedService.php` — single service, builds + paginates the unified feed
- `app/Http/Controllers/AdminNotificationFeedController.php` — `index()` only
- `tests/Unit/Services/AdminNotificationFeedServiceTest.php`
- `tests/Feature/AdminNotificationFeedControllerTest.php`

**New files (TS/React):**
- `resources/js/pages/notifications/index.tsx`
- `resources/js/pages/notifications/types.ts` — exported row + filter types
- `resources/js/pages/notifications/icons.ts` — type → icon mapping (kept tiny + testable)

**Modified files:**
- `routes/web.php` — add route inside the existing `/dashboard` group
- `resources/js/components/app-sidebar.tsx` — add top-level "Notifications" entry between Dashboard and User Management

---

## Task 1: Service skeleton + vendor application rows

**Files:**
- Create: `app/Services/AdminNotificationFeedService.php`
- Test: `tests/Unit/Services/AdminNotificationFeedServiceTest.php`

This task only handles the `VendorApplication` source. Tier upgrades and field-agent rows arrive in tasks 2 and 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use App\Services\AdminNotificationFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_application_submitted_then_approved_yields_two_rows(): void
    {
        $vendor = User::factory()->create(['name' => 'Akwasi Mensah']);
        $application = VendorApplication::factory()
            ->for($vendor)
            ->create([
                'submitted_at' => now()->subHours(2),
                'status' => VendorApplication::STATUS_APPROVED,
                'updated_at' => now()->subMinutes(10),
            ]);

        $service = app(AdminNotificationFeedService::class);
        $page = $service->feed(categories: [], perPage: 50, page: 1);

        $rows = $page->items();

        $this->assertCount(2, $rows);

        $approved = $rows[0];
        $submitted = $rows[1];

        $this->assertSame('vendor_onboarding', $approved['category']);
        $this->assertSame('approved', $approved['type']);
        $this->assertSame("vendor_application:{$application->id}:approved", $approved['id']);
        $this->assertSame($vendor->id, $approved['actor']['id']);
        $this->assertSame('Akwasi Mensah', $approved['actor']['name']);
        $this->assertSame($application->id, $approved['subject']['id']);
        $this->assertSame('vendor_application', $approved['subject']['type']);
        $this->assertStringContainsString('Akwasi Mensah', $approved['subject']['label']);
        $this->assertSame("/dashboard/vendor-applications/{$application->id}", $approved['action_url']);

        $this->assertSame('submitted', $submitted['type']);
        $this->assertSame("vendor_application:{$application->id}:submitted", $submitted['id']);
    }

    public function test_paid_event_uses_successful_payment_paid_at(): void
    {
        $vendor = User::factory()->create();
        $application = VendorApplication::factory()->for($vendor)->create([
            'submitted_at' => null,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        VendorOnboardingPayment::factory()->create([
            'vendor_application_id' => $application->id,
            'status' => VendorOnboardingPayment::STATUS_SUCCESS,
            'paid_at' => now()->subHour(),
        ]);

        VendorOnboardingPayment::factory()->create([
            'vendor_application_id' => $application->id,
            'status' => VendorOnboardingPayment::STATUS_FAILED,
            'paid_at' => null,
        ]);

        $page = app(AdminNotificationFeedService::class)->feed([], 50, 1);
        $types = collect($page->items())->pluck('type')->all();

        $this->assertContains('paid', $types);
        $this->assertCount(1, array_filter($types, fn ($t) => $t === 'paid'));
    }

    public function test_flag_lifecycle_yields_three_rows(): void
    {
        $application = VendorApplication::factory()->create([
            'submitted_at' => null,
            'status' => VendorApplication::STATUS_FLAGGED,
            'flagged_at' => now()->subDays(3),
            'flag_reminder_sent_at' => now()->subDays(2),
            'flag_expired_alert_sent_at' => now()->subDay(),
        ]);

        $rows = app(AdminNotificationFeedService::class)->feed([], 50, 1)->items();
        $types = collect($rows)->pluck('type')->all();

        $this->assertContains('flagged', $types);
        $this->assertContains('flag_reminded', $types);
        $this->assertContains('flag_expired', $types);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: ERRORS — `Class "App\Services\AdminNotificationFeedService" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services;

use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminNotificationFeedService
{
    /**
     * Build the system-wide admin notification feed.
     *
     * @param  array<int, string>  $categories  subset of ['vendor_onboarding','tier_upgrade','field_agent']; empty means all
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function feed(array $categories = [], int $perPage = 30, int $page = 1): LengthAwarePaginator
    {
        $rows = $this->vendorApplicationRows();

        $rows = $rows
            ->sortByDesc(fn (array $row) => [$row['occurred_at'], $row['id']])
            ->values();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values()->all(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function vendorApplicationRows(): Collection
    {
        $applications = VendorApplication::query()
            ->with(['user:id,name', 'payments' => fn ($q) => $q->where('status', VendorOnboardingPayment::STATUS_SUCCESS)])
            ->get();

        return $applications->flatMap(function (VendorApplication $app): array {
            $rows = [];
            $actor = $app->user ? ['id' => $app->user->id, 'name' => $app->user->name] : null;
            $tierLabel = $app->has_business_certificate ? 'Tier 1 (Business)' : 'Tier 2 (Individual)';
            $subject = [
                'id' => $app->id,
                'type' => 'vendor_application',
                'label' => trim(($app->user?->name ?? 'Unknown vendor').' — '.$tierLabel),
            ];
            $url = "/dashboard/vendor-applications/{$app->id}";

            $emit = function (string $type, ?\Carbon\Carbon $at) use (&$rows, $app, $actor, $subject, $url): void {
                if ($at === null) {
                    return;
                }
                $rows[] = [
                    'id' => "vendor_application:{$app->id}:{$type}",
                    'category' => 'vendor_onboarding',
                    'type' => $type,
                    'occurred_at' => $at->toIso8601String(),
                    'actor' => $actor,
                    'subject' => $subject,
                    'action_url' => $url,
                ];
            };

            $emit('submitted', $app->submitted_at);

            $successfulPayment = $app->payments->first();
            $emit('paid', $successfulPayment?->paid_at);

            if ($app->status === VendorApplication::STATUS_APPROVED) {
                $emit('approved', $app->updated_at);
            }
            if ($app->status === VendorApplication::STATUS_REJECTED) {
                $emit('rejected', $app->updated_at);
            }

            $emit('flagged', $app->flagged_at);
            $emit('flag_reminded', $app->flag_reminder_sent_at);
            $emit('flag_expired', $app->flag_expired_alert_sent_at);

            return $rows;
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: `OK ... Tests: 3, Assertions: ≥10`.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app vendor/bin/pint --dirty --format agent
git add app/Services/AdminNotificationFeedService.php tests/Unit/Services/AdminNotificationFeedServiceTest.php
git commit -m "feat(notifications): add admin feed service for vendor application events

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Tier upgrade rows

**Files:**
- Modify: `app/Services/AdminNotificationFeedService.php`
- Modify: `tests/Unit/Services/AdminNotificationFeedServiceTest.php`

- [ ] **Step 1: Add failing tests**

Append to `AdminNotificationFeedServiceTest`:

```php
public function test_tier_upgrade_submitted_paid_rejected_yields_three_rows(): void
{
    $vendor = User::factory()->create(['name' => 'Ama Boateng']);
    $request = \App\Models\TierUpgradeRequest::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => \App\Models\TierUpgradeRequest::STATUS_REJECTED,
        'created_at' => now()->subDays(5),
        'payment_verified_at' => now()->subDays(4),
        'reviewed_at' => now()->subDays(2),
    ]);

    $rows = app(AdminNotificationFeedService::class)->feed([], 50, 1)->items();
    $types = collect($rows)
        ->where('category', 'tier_upgrade')
        ->pluck('type')
        ->all();

    $this->assertEqualsCanonicalizing(['submitted', 'paid', 'rejected'], $types);
    $first = collect($rows)->firstWhere('category', 'tier_upgrade');
    $this->assertSame($vendor->id, $first['actor']['id']);
    $this->assertSame('tier_upgrade_request', $first['subject']['type']);
    $this->assertSame($request->id, $first['subject']['id']);
    $this->assertSame("/dashboard/tier-upgrades/{$request->id}", $first['action_url']);
}

public function test_tier_upgrade_pending_review_does_not_emit_terminal_rows(): void
{
    \App\Models\TierUpgradeRequest::factory()->create([
        'status' => \App\Models\TierUpgradeRequest::STATUS_PENDING_REVIEW,
        'created_at' => now(),
        'payment_verified_at' => now(),
        'reviewed_at' => null,
    ]);

    $types = collect(app(AdminNotificationFeedService::class)->feed([], 50, 1)->items())
        ->where('category', 'tier_upgrade')
        ->pluck('type')
        ->all();

    $this->assertNotContains('approved', $types);
    $this->assertNotContains('rejected', $types);
}
```

- [ ] **Step 2: Run tests — verify only the new ones fail**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: previous 3 still pass; 2 new ones fail with assertion errors.

- [ ] **Step 3: Add tier upgrade rows to the service**

Edit `feed()` to merge tier rows in:

```php
$rows = $this->vendorApplicationRows()->concat($this->tierUpgradeRows());
```

Add the new private method below `vendorApplicationRows()`:

```php
/**
 * @return Collection<int, array<string, mixed>>
 */
private function tierUpgradeRows(): Collection
{
    $requests = \App\Models\TierUpgradeRequest::query()
        ->with('vendor:id,name')
        ->get();

    return $requests->flatMap(function (\App\Models\TierUpgradeRequest $req): array {
        $rows = [];
        $actor = $req->vendor ? ['id' => $req->vendor->id, 'name' => $req->vendor->name] : null;
        $subject = [
            'id' => $req->id,
            'type' => 'tier_upgrade_request',
            'label' => trim(($req->vendor?->name ?? 'Unknown vendor').' — Tier Upgrade'),
        ];
        $url = "/dashboard/tier-upgrades/{$req->id}";

        $emit = function (string $type, ?\Carbon\Carbon $at) use (&$rows, $req, $actor, $subject, $url): void {
            if ($at === null) {
                return;
            }
            $rows[] = [
                'id' => "tier_upgrade_request:{$req->id}:{$type}",
                'category' => 'tier_upgrade',
                'type' => $type,
                'occurred_at' => $at->toIso8601String(),
                'actor' => $actor,
                'subject' => $subject,
                'action_url' => $url,
            ];
        };

        $emit('submitted', $req->created_at);
        $emit('paid', $req->payment_verified_at);
        if ($req->status === \App\Models\TierUpgradeRequest::STATUS_APPROVED) {
            $emit('approved', $req->reviewed_at);
        }
        if ($req->status === \App\Models\TierUpgradeRequest::STATUS_REJECTED) {
            $emit('rejected', $req->reviewed_at);
        }

        return $rows;
    });
}
```

Add the import at the top of the file: `use App\Models\TierUpgradeRequest;` and replace the fully-qualified references inside the new method with `TierUpgradeRequest::class` etc.

- [ ] **Step 4: Run tests — all pass**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: `OK` with 5 tests passing.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app vendor/bin/pint --dirty --format agent
git add app/Services/AdminNotificationFeedService.php tests/Unit/Services/AdminNotificationFeedServiceTest.php
git commit -m "feat(notifications): add tier upgrade events to admin feed

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Field-agent application rows + filter contract

**Files:**
- Modify: `app/Services/AdminNotificationFeedService.php`
- Modify: `tests/Unit/Services/AdminNotificationFeedServiceTest.php`

- [ ] **Step 1: Add failing tests**

Append:

```php
public function test_field_agent_application_approved_yields_submitted_and_approved(): void
{
    $applicant = User::factory()->create(['name' => 'Yaw Owusu']);
    $app = \App\Models\FieldAgentApplication::factory()->create([
        'user_id' => $applicant->id,
        'status' => \App\Enums\FieldAgentApplicationStatus::Approved,
        'created_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
    ]);

    $rows = app(AdminNotificationFeedService::class)->feed([], 50, 1)->items();
    $faRows = collect($rows)->where('category', 'field_agent')->values();

    $this->assertCount(2, $faRows);
    $this->assertEqualsCanonicalizing(['submitted', 'approved'], $faRows->pluck('type')->all());
    $this->assertSame($applicant->id, $faRows[0]['actor']['id']);
    $this->assertSame('field_agent_application', $faRows[0]['subject']['type']);
    $this->assertSame($app->id, $faRows[0]['subject']['id']);
    $this->assertSame("/dashboard/field-agent-applications/{$app->id}", $faRows[0]['action_url']);
}

public function test_filter_excludes_other_categories(): void
{
    VendorApplication::factory()->create(['submitted_at' => now()]);
    \App\Models\TierUpgradeRequest::factory()->create(['created_at' => now()]);
    \App\Models\FieldAgentApplication::factory()->create(['created_at' => now()]);

    $rows = app(AdminNotificationFeedService::class)
        ->feed(['vendor_onboarding'], 50, 1)
        ->items();

    $categories = collect($rows)->pluck('category')->unique()->values()->all();
    $this->assertSame(['vendor_onboarding'], $categories);
}

public function test_pagination_respects_per_page_and_page(): void
{
    foreach (range(1, 5) as $i) {
        VendorApplication::factory()->create([
            'submitted_at' => now()->subMinutes($i),
        ]);
    }

    $page1 = app(AdminNotificationFeedService::class)->feed([], 2, 1);
    $page2 = app(AdminNotificationFeedService::class)->feed([], 2, 2);

    $this->assertCount(2, $page1->items());
    $this->assertCount(2, $page2->items());
    $this->assertSame(5, $page1->total());
    $this->assertNotEquals(
        collect($page1->items())->pluck('id')->all(),
        collect($page2->items())->pluck('id')->all(),
    );
}
```

- [ ] **Step 2: Run tests — new ones fail**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: previous 5 pass; 3 new fail.

- [ ] **Step 3: Add field-agent source + filter logic**

Add `use App\Enums\FieldAgentApplicationStatus;` and `use App\Models\FieldAgentApplication;` at top.

Update `feed()`:

```php
public function feed(array $categories = [], int $perPage = 30, int $page = 1): LengthAwarePaginator
{
    $allowed = ['vendor_onboarding', 'tier_upgrade', 'field_agent'];
    $selected = empty($categories) ? $allowed : array_values(array_intersect($categories, $allowed));

    $rows = collect();
    if (in_array('vendor_onboarding', $selected, true)) {
        $rows = $rows->concat($this->vendorApplicationRows());
    }
    if (in_array('tier_upgrade', $selected, true)) {
        $rows = $rows->concat($this->tierUpgradeRows());
    }
    if (in_array('field_agent', $selected, true)) {
        $rows = $rows->concat($this->fieldAgentRows());
    }

    $rows = $rows
        ->sortByDesc(fn (array $row) => [$row['occurred_at'], $row['id']])
        ->values();

    return new LengthAwarePaginator(
        items: $rows->forPage($page, $perPage)->values()->all(),
        total: $rows->count(),
        perPage: $perPage,
        currentPage: $page,
    );
}
```

Add `fieldAgentRows()`:

```php
/**
 * @return Collection<int, array<string, mixed>>
 */
private function fieldAgentRows(): Collection
{
    $applications = FieldAgentApplication::query()
        ->with('user:id,name')
        ->get();

    return $applications->flatMap(function (FieldAgentApplication $app): array {
        $rows = [];
        $actor = $app->user ? ['id' => $app->user->id, 'name' => $app->user->name] : null;
        $subject = [
            'id' => $app->id,
            'type' => 'field_agent_application',
            'label' => trim(($app->user?->name ?? trim($app->first_name.' '.$app->last_name)).' — Field Agent'),
        ];
        $url = "/dashboard/field-agent-applications/{$app->id}";

        $emit = function (string $type, ?\Carbon\Carbon $at) use (&$rows, $app, $actor, $subject, $url): void {
            if ($at === null) {
                return;
            }
            $rows[] = [
                'id' => "field_agent_application:{$app->id}:{$type}",
                'category' => 'field_agent',
                'type' => $type,
                'occurred_at' => $at->toIso8601String(),
                'actor' => $actor,
                'subject' => $subject,
                'action_url' => $url,
            ];
        };

        $emit('submitted', $app->created_at);
        if ($app->status === FieldAgentApplicationStatus::Approved) {
            $emit('approved', $app->reviewed_at);
        }
        if ($app->status === FieldAgentApplicationStatus::Rejected) {
            $emit('rejected', $app->reviewed_at);
        }

        return $rows;
    });
}
```

Note: if the actual case names in `App\Enums\FieldAgentApplicationStatus` differ (e.g. `APPROVED`/`REJECTED`), use those exact case identifiers instead — open the enum file once to confirm.

- [ ] **Step 4: Run all service tests — all pass**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedServiceTest --no-progress`
Expected: `OK` with 8 tests.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app vendor/bin/pint --dirty --format agent
git add app/Services/AdminNotificationFeedService.php tests/Unit/Services/AdminNotificationFeedServiceTest.php
git commit -m "feat(notifications): add field agent events and category filtering

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Controller, route, feature test

**Files:**
- Create: `app/Http/Controllers/AdminNotificationFeedController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminNotificationFeedControllerTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminNotificationFeedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_notifications_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorApplication::factory()->create(['submitted_at' => now()]);

        $this->actingAs($admin)
            ->get('/dashboard/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->has('feed.data', 1)
                ->where('filters.categories', [])
            );
    }

    public function test_super_admin_can_view_notifications_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get('/dashboard/notifications')
            ->assertOk();
    }

    public function test_non_admin_is_blocked(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($vendor)->get('/dashboard/notifications');

        // EnsureDashboardAccess redirects vendors away from /dashboard
        $this->assertNotEquals(200, $response->status());
    }

    public function test_unauthenticated_is_redirected(): void
    {
        $this->get('/dashboard/notifications')->assertRedirect();
    }

    public function test_category_filter_is_passed_through(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorApplication::factory()->create(['submitted_at' => now()]);
        \App\Models\TierUpgradeRequest::factory()->create(['created_at' => now()]);

        $this->actingAs($admin)
            ->get('/dashboard/notifications?categories=vendor_onboarding')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.categories', ['vendor_onboarding'])
                ->where('feed.data.0.category', 'vendor_onboarding')
                ->has('feed.data', 1)
            );
    }

    public function test_invalid_category_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard/notifications?categories[]=bogus')
            ->assertSessionHasErrors('categories.0');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedControllerTest --no-progress`
Expected: ERRORS — route `/dashboard/notifications` not defined.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\AdminNotificationFeedService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationFeedController extends Controller
{
    public function __construct(private readonly AdminNotificationFeedService $service) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'categories' => ['sometimes', 'array'],
            'categories.*' => [Rule::in(['vendor_onboarding', 'tier_upgrade', 'field_agent'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $rawCategories = $request->input('categories', []);
        if (is_string($rawCategories)) {
            $rawCategories = array_filter(array_map('trim', explode(',', $rawCategories)));
        }

        $page = (int) ($validated['page'] ?? 1);

        $feed = $this->service->feed(
            categories: $rawCategories,
            perPage: 30,
            page: $page,
        );

        return Inertia::render('notifications/index', [
            'feed' => $feed,
            'filters' => [
                'categories' => array_values($rawCategories),
            ],
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

Open `routes/web.php`, find the existing block:

```php
Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(function () {
```

Inside that group (anywhere is fine; near the top reads cleanly), add:

```php
    Route::get('/notifications', [\App\Http\Controllers\AdminNotificationFeedController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('admin.notifications.index');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit --filter=AdminNotificationFeedControllerTest --no-progress`
Expected: `OK` with 6 tests.

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec -T app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/AdminNotificationFeedController.php routes/web.php tests/Feature/AdminNotificationFeedControllerTest.php
git commit -m "feat(notifications): add admin notifications page route + controller

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Frontend page + sidebar entry

**Files:**
- Create: `resources/js/pages/notifications/types.ts`
- Create: `resources/js/pages/notifications/icons.ts`
- Create: `resources/js/pages/notifications/index.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

This is UI-only; the assertions live in the manual smoke step. Backend tests already cover the data path.

- [ ] **Step 1: Create the row + filter types**

`resources/js/pages/notifications/types.ts`:

```ts
export type FeedCategory = 'vendor_onboarding' | 'tier_upgrade' | 'field_agent';

export type FeedRowType =
    | 'submitted'
    | 'paid'
    | 'approved'
    | 'rejected'
    | 'flagged'
    | 'flag_reminded'
    | 'flag_expired';

export interface FeedRow {
    id: string;
    category: FeedCategory;
    type: FeedRowType;
    occurred_at: string;
    actor: { id: number; name: string } | null;
    subject: { id: number; type: string; label: string };
    action_url: string;
}

export interface FeedPaginator {
    data: FeedRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface NotificationsPageProps {
    feed: FeedPaginator;
    filters: { categories: FeedCategory[] };
}
```

- [ ] **Step 2: Create the icon mapping**

`resources/js/pages/notifications/icons.ts`:

```ts
import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    XCircle,
} from 'lucide-react';
import type { FeedRowType } from './types';

export const ICON_FOR_TYPE: Record<FeedRowType, typeof FileText> = {
    submitted: FileText,
    paid: CreditCard,
    approved: CheckCircle2,
    rejected: XCircle,
    flagged: AlertTriangle,
    flag_reminded: BellRing,
    flag_expired: Clock,
};

export const TITLE_FOR_TYPE: Record<FeedRowType, string> = {
    submitted: 'submitted application',
    paid: 'completed onboarding payment',
    approved: 'approved',
    rejected: 'rejected',
    flagged: 'flagged',
    flag_reminded: 'flag reminder sent',
    flag_expired: 'flag expired',
};
```

- [ ] **Step 3: Build the page**

`resources/js/pages/notifications/index.tsx`:

```tsx
import DashboardLayout from '@/layouts/dashboard-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ICON_FOR_TYPE, TITLE_FOR_TYPE } from './icons';
import type { FeedCategory, FeedRow, NotificationsPageProps } from './types';

const ALL_CATEGORIES: { id: FeedCategory; label: string }[] = [
    { id: 'vendor_onboarding', label: 'Vendor Onboarding' },
    { id: 'tier_upgrade', label: 'Tier Upgrades' },
    { id: 'field_agent', label: 'Field Agents' },
];

function bucketOf(iso: string): 'today' | 'yesterday' | 'last7' | 'older' {
    const now = new Date();
    const at = new Date(iso);
    const days = Math.floor((now.getTime() - at.getTime()) / 86_400_000);
    if (days <= 0 && now.getDate() === at.getDate()) {
        return 'today';
    }
    if (days <= 1) {
        return 'yesterday';
    }
    if (days <= 7) {
        return 'last7';
    }
    return 'older';
}

const BUCKET_LABEL: Record<ReturnType<typeof bucketOf>, string> = {
    today: 'Today',
    yesterday: 'Yesterday',
    last7: 'Last 7 days',
    older: 'Older',
};

function formatRelative(iso: string): string {
    const at = new Date(iso);
    const diffSec = Math.floor((Date.now() - at.getTime()) / 1000);
    if (diffSec < 60) return `${diffSec}s ago`;
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 86_400) return `${Math.floor(diffSec / 3600)}h ago`;
    return `${Math.floor(diffSec / 86_400)}d ago`;
}

function toggleCategory(current: FeedCategory[], cat: FeedCategory): FeedCategory[] {
    return current.includes(cat) ? current.filter((c) => c !== cat) : [...current, cat];
}

export default function NotificationsIndex({ feed, filters }: NotificationsPageProps) {
    const updateFilter = (next: FeedCategory[]) => {
        router.get('/dashboard/notifications', { categories: next }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const grouped = feed.data.reduce<Record<string, FeedRow[]>>((acc, row) => {
        const key = bucketOf(row.occurred_at);
        (acc[key] ??= []).push(row);
        return acc;
    }, {});

    return (
        <DashboardLayout>
            <Head title="Notifications" />

            <div className="px-6 py-4 space-y-4">
                <h1 className="text-2xl font-semibold">Notifications</h1>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => updateFilter([])}
                        className={`rounded-full border px-3 py-1 text-sm ${filters.categories.length === 0 ? 'bg-primary text-primary-foreground' : ''}`}
                    >
                        All
                    </button>
                    {ALL_CATEGORIES.map((c) => {
                        const active = filters.categories.includes(c.id);
                        return (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => updateFilter(toggleCategory(filters.categories, c.id))}
                                className={`rounded-full border px-3 py-1 text-sm ${active ? 'bg-primary text-primary-foreground' : ''}`}
                            >
                                {c.label}
                            </button>
                        );
                    })}
                </div>

                {feed.data.length === 0 && (
                    <p className="text-muted-foreground py-12 text-center">No notifications yet.</p>
                )}

                {(['today', 'yesterday', 'last7', 'older'] as const).map((bucket) => {
                    const rows = grouped[bucket];
                    if (!rows || rows.length === 0) return null;
                    return (
                        <section key={bucket} className="space-y-2">
                            <h2 className="text-muted-foreground text-xs font-medium uppercase tracking-wider">
                                {BUCKET_LABEL[bucket]}
                            </h2>
                            <ul className="divide-y rounded-lg border">
                                {rows.map((row) => {
                                    const Icon = ICON_FOR_TYPE[row.type];
                                    return (
                                        <li key={row.id}>
                                            <Link
                                                href={row.action_url}
                                                className="hover:bg-muted/40 flex items-start gap-3 px-4 py-3"
                                            >
                                                <Icon className="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm">
                                                        <span className="font-medium">{row.actor?.name ?? 'Someone'}</span>{' '}
                                                        {TITLE_FOR_TYPE[row.type]}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-xs">{row.subject.label}</p>
                                                </div>
                                                <span className="text-muted-foreground shrink-0 text-xs">
                                                    {formatRelative(row.occurred_at)}
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    );
                })}

                {feed.current_page < feed.last_page && (
                    <div className="flex justify-center pt-4">
                        <Link
                            href={`/dashboard/notifications?page=${feed.current_page + 1}${filters.categories.length ? `&categories=${filters.categories.join(',')}` : ''}`}
                            preserveScroll
                            className="rounded-md border px-4 py-2 text-sm"
                        >
                            Load more
                        </Link>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
```

If the codebase's existing pages import the dashboard layout from a different path (`@/Layouts/...` vs `@/layouts/...`), open one existing admin page (e.g. `resources/js/pages/payments/index.tsx`) and copy the import line verbatim — match the existing convention exactly.

- [ ] **Step 4: Add the sidebar entry**

Open `resources/js/components/app-sidebar.tsx`. Find the existing top-level group around line 53:

```tsx
{
    title: 'Dashboard',
    href: dashboard(),
},
```

Immediately after that object, add:

```tsx
{
    title: 'Notifications',
    href: '/dashboard/notifications',
    icon: Bell,
},
```

Make sure `Bell` is imported from `lucide-react` at the top of the file (it usually is — check the existing imports first; if not, add it).

- [ ] **Step 5: Build + manual smoke**

```bash
docker compose exec -T app pnpm run build
```

Then in a browser:
1. Log in as `xylaray37@gmail.com` / `Gilash@123` (super-admin per CLAUDE.md).
2. Visit `/dashboard/notifications`.
3. Verify "Notifications" appears in the left sidebar between Dashboard and User Management.
4. Verify the page lists at least one row (run via the seeded data; if empty, submit a vendor application via the API to generate one).
5. Toggle each filter chip — URL updates and list filters.
6. Click a row — navigates to the relevant detail page.

Document any issues as a follow-up.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/notifications resources/js/components/app-sidebar.tsx
git commit -m "feat(notifications): add admin notifications page UI + sidebar entry

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Final verification

- [ ] **Step 1: Run the full notification + feed test suite**

```bash
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=surprise_moi_db_tests app vendor/bin/phpunit \
    --filter='AdminNotificationFeedServiceTest|AdminNotificationFeedControllerTest|VendorApplicationSubmittedNotificationTest' \
    --no-progress
```

Expected: all green (≥17 tests).

- [ ] **Step 2: Pint full repo check**

```bash
docker compose exec -T app vendor/bin/pint --test --format agent
```

Expected: `{"result":"pass"}`.

- [ ] **Step 3: Push branch**

```bash
git push -u origin feat/admin-notifications-page
```

- [ ] **Step 4: Open PR (optional)**

Use `gh pr create` per CLAUDE.md conventions. Title: `feat(notifications): admin notifications page`. Body: link the spec doc.
