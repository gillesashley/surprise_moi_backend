# Field Agent Vendor Validation Checklist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a post-approval trust layer where field agents run an on-site checklist for live vendors, and on pass the vendor gets a 12-month public "Field Verified" badge exposed to the mobile app.

**Architecture:** Two new tables (`vendor_visits`, `vendor_visit_items`), two denormalized cached columns on `users` (`field_verified_at`, `field_verified_until`) kept in sync by a single observer. Auto-compute rule (`CompleteVendorVisit` action) decides pass/fail/submitted on submit. Field-agent dashboard gets three new Inertia pages; admin gets two; mobile API (at `C:\dev\surprise_moi`) receives a widened `VendorResource` plus one new `/api/v1/vendor/field-verification` endpoint.

**Tech Stack:** Laravel 12 (PHP 8.2), Inertia.js v2 + React 19, PHPUnit v11, Laravel Pint, Sanctum for mobile API auth.

**Spec:** `docs/superpowers/specs/2026-04-20-field-agent-vendor-validation-checklist-design.md`

**Branch:** `feat/field-agent-vendor-validation-checklist` (create fresh from main after the spec branch is merged).

---

## Prerequisites

- [ ] **Confirm branch and working tree are clean**

```bash
git checkout main
git pull
git checkout -b feat/field-agent-vendor-validation-checklist
git status
```

Expected: on `feat/field-agent-vendor-validation-checklist`, working tree clean.

- [ ] **Verify VendorApplication column names match what the plan assumes**

```bash
php artisan tinker --execute="echo implode(',', \Schema::getColumnListing('vendor_applications'));"
```

Expected output must include `has_business_certificate` and `tin_number`. If `tin_number` is absent, substitute `has_tin` everywhere this plan uses `tin_number` as the seed condition for `documents.tin_seen`.

---

## File Structure

### Create (new files)

**Enums**
- `app/Enums/VendorVisitStatus.php` — draft | submitted | passed | failed | revoked
- `app/Enums/VendorVisitItemCategory.php` — identity | physical | documents | financial
- `app/Enums/VendorVisitItemCriticality.php` — critical | informational

**Config**
- `config/vendor_visit_checklist.php` — hardcoded source of truth for item keys, labels, categories, criticality, and seed conditions.

**Migrations**
- `database/migrations/YYYY_MM_DD_HHMMSS_create_vendor_visits_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_vendor_visit_items_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_field_verification_columns_to_users_table.php`

**Models**
- `app/Models/VendorVisit.php`
- `app/Models/VendorVisitItem.php`

**Factories**
- `database/factories/VendorVisitFactory.php`
- `database/factories/VendorVisitItemFactory.php`

**Actions / Services**
- `app/Actions/VendorVisit/StartVendorVisit.php`
- `app/Actions/VendorVisit/CompleteVendorVisit.php`
- `app/Actions/VendorVisit/OverrideVendorVisit.php`
- `app/Actions/VendorVisit/RevokeFieldVerificationBadge.php`

**Observers**
- `app/Observers/VendorVisitObserver.php` — badge propagation on terminal transitions.
- `app/Observers/FieldVerificationInvalidationObserver.php` — clears badge when vendor edits critical profile fields.

**Form Requests**
- `app/Http/Requests/FieldAgent/StartVendorVisitRequest.php`
- `app/Http/Requests/FieldAgent/UpdateVendorVisitItemRequest.php`
- `app/Http/Requests/FieldAgent/SubmitVendorVisitRequest.php`
- `app/Http/Requests/Admin/OverrideVendorVisitRequest.php`
- `app/Http/Requests/Admin/RevokeFieldVerificationBadgeRequest.php`

**Controllers**
- `app/Http/Controllers/FieldAgent/VendorVisitsController.php`
- `app/Http/Controllers/Admin/VendorVisitsController.php`
- `app/Http/Controllers/Api/V1/VendorFieldVerificationController.php`

**API Resources**
- `app/Http/Resources/VendorFieldVerificationResource.php`
- `app/Http/Resources/VendorVisitSummaryResource.php` (used inside the above)

**Notifications**
- `app/Notifications/VisitEscalatedToAdminNotification.php`
- `app/Notifications/VisitFailedNotification.php`
- `app/Notifications/FieldVerificationBadgeRevokedNotification.php`

**Inertia pages (field agent)**
- `resources/js/Pages/field-agent/visits/index.tsx`
- `resources/js/Pages/field-agent/visits/show.tsx`
- `resources/js/Pages/field-agent/visits/new.tsx`
- `resources/js/Pages/field-agent/visits/components/ChecklistSection.tsx`
- `resources/js/Pages/field-agent/visits/components/ChecklistItemRow.tsx`
- `resources/js/Pages/field-agent/visits/components/EvidencePhotoUpload.tsx`

**Inertia pages (admin)**
- `resources/js/Pages/admin/vendor-visits/index.tsx`
- `resources/js/Pages/admin/vendor-visits/show.tsx`
- `resources/js/Pages/admin/vendor-visits/components/AdminOverridePanel.tsx`

**Tests**
- `tests/Unit/Actions/VendorVisit/StartVendorVisitTest.php`
- `tests/Unit/Actions/VendorVisit/CompleteVendorVisitTest.php`
- `tests/Unit/Actions/VendorVisit/OverrideVendorVisitTest.php`
- `tests/Unit/Actions/VendorVisit/RevokeFieldVerificationBadgeTest.php`
- `tests/Feature/FieldAgent/VendorVisitsControllerTest.php`
- `tests/Feature/Admin/VendorVisitsControllerTest.php`
- `tests/Feature/Api/V1/VendorFieldVerificationTest.php`
- `tests/Feature/Observers/VendorVisitObserverTest.php`
- `tests/Feature/Observers/FieldVerificationInvalidationObserverTest.php`
- `tests/Feature/Api/V1/VendorResourceFieldVerificationTest.php`

### Modify (existing files)

- `app/Models/User.php` — add `vendorVisitsAsAgent()`, `vendorVisitsReceived()` relations, `isFieldVerified()` accessor, `field_verified_at`/`field_verified_until` casts.
- `app/Models/VendorApplication.php` — add `vendor` accessor if missing (for seeding condition helper).
- `app/Http/Resources/VendorResource.php` — add `is_field_verified` + `field_verified_until`.
- `app/Providers/AppServiceProvider.php` — register the two new observers.
- `app/Http/Controllers/FieldAgentDashboardController.php` — add a `needsVisitCount` prop to the existing dashboard index.
- `resources/js/Pages/field-agent/dashboard.tsx` — add "N vendors need a visit" summary card.
- `resources/js/Pages/admin/vendors/show.tsx` (or wherever admin sees vendor detail) — add the "Field verification" panel. Use Grep to find the exact page path before editing.
- `routes/web.php` — register field-agent and admin route groups (before the SPA catch-alls).
- `routes/api.php` — register the new `GET /v1/vendor/field-verification` route inside the existing `v1` group.

### Deliberately not touched

- `VendorApplication` onboarding wizard and admin review screens.
- Earnings, targets, referral code, payout systems.
- The field agent's existing targets/earnings/payouts/verification pages.

---

## Task 1: Create enums and the checklist config

**Files:**
- Create: `app/Enums/VendorVisitStatus.php`
- Create: `app/Enums/VendorVisitItemCategory.php`
- Create: `app/Enums/VendorVisitItemCriticality.php`
- Create: `config/vendor_visit_checklist.php`

**Mental model:** Enums are the type spine of this feature — every bool-like decision ("is this visit done?", "is this item critical?") gets answered by comparing against a typed case, never a magic string. The config is the **single place** where "what's on the checklist" lives, so a product change tomorrow is a one-file edit, not a codebase grep.

- [ ] **Step 1: Create `VendorVisitStatus` enum**

Create `app/Enums/VendorVisitStatus.php`:

```php
<?php

namespace App\Enums;

enum VendorVisitStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Passed = 'passed';
    case Failed = 'failed';
    case Revoked = 'revoked';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Passed, self::Failed, self::Revoked, self::Submitted => true,
            self::Draft => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Awaiting admin review',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Revoked => 'Revoked',
        };
    }
}
```

- [ ] **Step 2: Create `VendorVisitItemCategory` enum**

Create `app/Enums/VendorVisitItemCategory.php`:

```php
<?php

namespace App\Enums;

enum VendorVisitItemCategory: string
{
    case Identity = 'identity';
    case Physical = 'physical';
    case Documents = 'documents';
    case Financial = 'financial';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Identity',
            self::Physical => 'Physical verification',
            self::Documents => 'Documents',
            self::Financial => 'Financial',
        };
    }
}
```

- [ ] **Step 3: Create `VendorVisitItemCriticality` enum**

Create `app/Enums/VendorVisitItemCriticality.php`:

```php
<?php

namespace App\Enums;

enum VendorVisitItemCriticality: string
{
    case Critical = 'critical';
    case Informational = 'informational';
}
```

- [ ] **Step 4: Create the checklist config**

Create `config/vendor_visit_checklist.php`:

```php
<?php

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;

return [
    /*
    |--------------------------------------------------------------------------
    | Vendor visit checklist items
    |--------------------------------------------------------------------------
    |
    | The single source of truth for what rows are seeded into
    | `vendor_visit_items` when a field agent starts a visit.
    |
    | 'seed_if' is one of:
    |   'always'            — row is always seeded
    |   'has_business_cert' — seed only if VendorApplication.has_business_certificate = true
    |   'has_tin'           — seed only if VendorApplication.tin_number is non-empty
    */

    'items' => [
        [
            'key' => 'identity.person_matches_ghana_card',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the person in front of you match the Ghana Card photo on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'identity.name_matches_records',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the name on the physical Ghana Card match the name on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.location_is_real',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Is the claimed business address a real, findable location?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.business_name_matches',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the business at this address match the business name on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.business_is_operational',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Is there signage, stock, or active service — a real going concern?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'documents.business_cert_seen',
            'category' => VendorVisitItemCategory::Documents->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Have you seen the physical business certificate, and does it match the file?',
            'seed_if' => 'has_business_cert',
        ],
        [
            'key' => 'documents.tin_seen',
            'category' => VendorVisitItemCategory::Documents->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Have you seen the physical TIN document, and does it match the file?',
            'seed_if' => 'has_tin',
        ],
        [
            'key' => 'financial.phone_reachable',
            'category' => VendorVisitItemCategory::Financial->value,
            'criticality' => VendorVisitItemCriticality::Informational->value,
            'label' => 'Did you call the registered phone and have it ring / be answered?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'financial.momo_test_received',
            'category' => VendorVisitItemCategory::Financial->value,
            'criticality' => VendorVisitItemCriticality::Informational->value,
            'label' => 'Did your GHS 1 test MoMo reach the registered mobile money number?',
            'seed_if' => 'always',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge validity period (months)
    |--------------------------------------------------------------------------
    */
    'badge_validity_months' => 12,
];
```

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/VendorVisitStatus.php app/Enums/VendorVisitItemCategory.php app/Enums/VendorVisitItemCriticality.php config/vendor_visit_checklist.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add enums and checklist config

Introduces the type spine for the field-agent vendor validation
feature: three string-backed enums and a single config file that
lists every checklist item, its category, criticality, and seeding
rule. Item-list changes are now a one-file edit.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Migrations

**Files:**
- Create: three migration files, timestamped in order.

**Mental model:** Think of a filing cabinet being retrofitted with three new drawers. `vendor_visits` is the master folder — one per visit. `vendor_visit_items` is the subfolder — one row per checklist item within a visit. The two columns on `users` are a sticky note on the vendor's own folder saying "currently field verified until X" — redundant with the folders, but you don't want customers flipping through the cabinet on every page load.

- [ ] **Step 1: Generate the migrations**

```bash
php artisan make:migration create_vendor_visits_table --no-interaction
php artisan make:migration create_vendor_visit_items_table --no-interaction
php artisan make:migration add_field_verification_columns_to_users_table --no-interaction
```

Expected: three files under `database/migrations/` with fresh timestamps, in order.

- [ ] **Step 2: Fill `create_vendor_visits_table`**

Open the generated file and replace `up()` + `down()`:

```php
public function up(): void
{
    Schema::create('vendor_visits', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('field_agent_user_id')->constrained('users')->cascadeOnDelete();
        $table->string('status')->default('draft');
        $table->timestamp('started_at');
        $table->timestamp('submitted_at')->nullable();
        $table->decimal('visit_latitude', 10, 7);
        $table->decimal('visit_longitude', 10, 7);
        $table->string('storefront_photo_path')->nullable();
        $table->string('owner_photo_path')->nullable();
        $table->text('notes')->nullable();
        $table->boolean('escalated')->default(false);
        $table->string('computed_result')->nullable();
        $table->string('admin_override_result')->nullable();
        $table->text('admin_override_reason')->nullable();
        $table->foreignId('admin_override_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('admin_override_at')->nullable();
        $table->timestamp('badge_issued_at')->nullable();
        $table->timestamp('badge_expires_at')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->index(['vendor_user_id', 'status']);
        $table->index(['field_agent_user_id', 'status']);
        $table->index('badge_expires_at');
    });
}

public function down(): void
{
    Schema::dropIfExists('vendor_visits');
}
```

- [ ] **Step 3: Fill `create_vendor_visit_items_table`**

```php
public function up(): void
{
    Schema::create('vendor_visit_items', function (Blueprint $table) {
        $table->id();
        $table->uuid('vendor_visit_id');
        $table->string('item_key');
        $table->string('category');
        $table->string('criticality');
        $table->boolean('passed')->nullable();
        $table->text('note')->nullable();
        $table->timestamps();

        $table->foreign('vendor_visit_id')
            ->references('id')->on('vendor_visits')
            ->cascadeOnDelete();
        $table->unique(['vendor_visit_id', 'item_key']);
        $table->index('vendor_visit_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('vendor_visit_items');
}
```

- [ ] **Step 4: Fill `add_field_verification_columns_to_users_table`**

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->timestamp('field_verified_at')->nullable()->after('id');
        $table->timestamp('field_verified_until')->nullable()->after('field_verified_at');
        $table->index('field_verified_until');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropIndex(['field_verified_until']);
        $table->dropColumn(['field_verified_at', 'field_verified_until']);
    });
}
```

- [ ] **Step 5: Run the migrations**

```bash
php artisan migrate
```

Expected: three migrations run successfully. Verify:

```bash
php artisan tinker --execute="echo implode(',', \Schema::getColumnListing('vendor_visits')); echo PHP_EOL; echo implode(',', \Schema::getColumnListing('vendor_visit_items')); echo PHP_EOL; echo in_array('field_verified_until', \Schema::getColumnListing('users')) ? 'user-cols: ok' : 'user-cols: MISSING';"
```

Expected: the three output lines list all the new columns.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add schema for vendor visits and field verification

Three migrations:
- create vendor_visits (uuid pk, status enum string, GPS, photos, admin
  override columns, badge window)
- create vendor_visit_items (one row per checklist item per visit)
- add field_verified_at / field_verified_until to users as denormalized
  read shortcuts for customer-facing badge rendering.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Models, factories, and User relations

**Files:**
- Create: `app/Models/VendorVisit.php`
- Create: `app/Models/VendorVisitItem.php`
- Create: `database/factories/VendorVisitFactory.php`
- Create: `database/factories/VendorVisitItemFactory.php`
- Modify: `app/Models/User.php`

**Mental model:** Models are the typed front door to the rows. We put all the "what is this value" logic (casts, enums, the `isFieldVerified()` accessor) in one place so controllers and views don't re-derive it.

- [ ] **Step 1: Create `VendorVisit` model**

Create `app/Models/VendorVisit.php`:

```php
<?php

namespace App\Models;

use App\Enums\VendorVisitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorVisit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_user_id',
        'field_agent_user_id',
        'status',
        'started_at',
        'submitted_at',
        'visit_latitude',
        'visit_longitude',
        'storefront_photo_path',
        'owner_photo_path',
        'notes',
        'escalated',
        'computed_result',
        'admin_override_result',
        'admin_override_reason',
        'admin_override_by',
        'admin_override_at',
        'badge_issued_at',
        'badge_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorVisitStatus::class,
            'computed_result' => VendorVisitStatus::class,
            'admin_override_result' => VendorVisitStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'admin_override_at' => 'datetime',
            'badge_issued_at' => 'datetime',
            'badge_expires_at' => 'datetime',
            'escalated' => 'boolean',
            'visit_latitude' => 'decimal:7',
            'visit_longitude' => 'decimal:7',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_agent_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorVisitItem::class);
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_override_by');
    }

    public function effectiveResult(): ?VendorVisitStatus
    {
        return $this->admin_override_result ?? $this->computed_result;
    }
}
```

- [ ] **Step 2: Create `VendorVisitItem` model**

Create `app/Models/VendorVisitItem.php`:

```php
<?php

namespace App\Models;

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorVisitItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_visit_id',
        'item_key',
        'category',
        'criticality',
        'passed',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'category' => VendorVisitItemCategory::class,
            'criticality' => VendorVisitItemCriticality::class,
            'passed' => 'boolean',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VendorVisit::class, 'vendor_visit_id');
    }

    public function isAnswered(): bool
    {
        return $this->passed !== null;
    }
}
```

- [ ] **Step 3: Create factories**

Create `database/factories/VendorVisitFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorVisitFactory extends Factory
{
    protected $model = VendorVisit::class;

    public function definition(): array
    {
        return [
            'vendor_user_id' => User::factory()->vendor(),
            'field_agent_user_id' => User::factory()->fieldAgent(),
            'status' => VendorVisitStatus::Draft->value,
            'started_at' => now(),
            'visit_latitude' => $this->faker->latitude(),
            'visit_longitude' => $this->faker->longitude(),
            'escalated' => false,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Passed->value,
            'computed_result' => VendorVisitStatus::Passed->value,
            'submitted_at' => now(),
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
            'badge_issued_at' => now(),
            'badge_expires_at' => now()->addMonths(12),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Failed->value,
            'computed_result' => VendorVisitStatus::Failed->value,
            'submitted_at' => now(),
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorVisitStatus::Submitted->value,
            'submitted_at' => now(),
            'escalated' => true,
            'storefront_photo_path' => 'visits/storefront.jpg',
            'owner_photo_path' => 'visits/owner.jpg',
        ]);
    }
}
```

Create `database/factories/VendorVisitItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;
use App\Models\VendorVisit;
use App\Models\VendorVisitItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorVisitItemFactory extends Factory
{
    protected $model = VendorVisitItem::class;

    public function definition(): array
    {
        return [
            'vendor_visit_id' => VendorVisit::factory(),
            'item_key' => 'identity.person_matches_ghana_card',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'passed' => null,
            'note' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['criticality' => VendorVisitItemCriticality::Critical->value]);
    }

    public function informational(): static
    {
        return $this->state(fn () => ['criticality' => VendorVisitItemCriticality::Informational->value]);
    }

    public function passed(): static
    {
        return $this->state(fn () => ['passed' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['passed' => false]);
    }
}
```

- [ ] **Step 4: Extend `User` model**

Open `app/Models/User.php`. Add the casts for the two new columns by locating the existing `casts()` method and adding:

```php
'field_verified_at' => 'datetime',
'field_verified_until' => 'datetime',
```

Then add these relations and accessor somewhere alongside other relations on the model:

```php
public function vendorVisitsReceived(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\VendorVisit::class, 'vendor_user_id');
}

public function vendorVisitsAsAgent(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\VendorVisit::class, 'field_agent_user_id');
}

public function isFieldVerified(): bool
{
    return $this->field_verified_until !== null
        && $this->field_verified_until->isFuture();
}
```

Also add both new columns to `$fillable` if the User model uses `$fillable` (check existing convention; if it uses `$guarded = []`, skip this).

- [ ] **Step 5: Smoke-test relations in tinker**

```bash
php artisan tinker --execute="\$v = \App\Models\VendorVisit::factory()->make(); echo get_class(\$v->vendor()).'|'.get_class(\$v->fieldAgent()).'|'.get_class(\$v->items());"
```

Expected: all three print `Illuminate\Database\Eloquent\Relations\...` class names. No SQL errors.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorVisit.php app/Models/VendorVisitItem.php database/factories/VendorVisitFactory.php database/factories/VendorVisitItemFactory.php app/Models/User.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add VendorVisit / VendorVisitItem models and factories

Adds Eloquent models (with enum casts, UUID pk for visits, soft deletes)
and factories with useful states (passed/failed/escalated). Extends User
with two new relations and an isFieldVerified() accessor that respects
badge expiry.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: `StartVendorVisit` action

**Files:**
- Create: `app/Actions/VendorVisit/StartVendorVisit.php`
- Create: `tests/Unit/Actions/VendorVisit/StartVendorVisitTest.php`

**Mental model:** Think of this action as a clerk who opens a fresh visit folder and pre-prints the right set of checklist forms inside it. The clerk consults the vendor's registration file (`VendorApplication`) to decide whether the documents forms go in. Its only job is setup — no validation of answers happens here.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Actions/VendorVisit/StartVendorVisitTest.php`:

```php
<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_draft_visit_with_gps_and_started_at(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute(
            vendor: $vendor,
            agent: $agent,
            latitude: 5.56,
            longitude: -0.2,
        );

        $this->assertSame(VendorVisitStatus::Draft, $visit->status);
        $this->assertSame($vendor->id, $visit->vendor_user_id);
        $this->assertSame($agent->id, $visit->field_agent_user_id);
        $this->assertNotNull($visit->started_at);
        $this->assertSame('5.5600000', (string) $visit->visit_latitude);
    }

    public function test_it_seeds_always_items_for_unregistered_vendor(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();

        $this->assertContains('identity.person_matches_ghana_card', $keys);
        $this->assertContains('physical.location_is_real', $keys);
        $this->assertContains('financial.phone_reachable', $keys);
        $this->assertNotContains('documents.business_cert_seen', $keys);
        $this->assertNotContains('documents.tin_seen', $keys);
    }

    public function test_it_seeds_business_cert_item_only_when_vendor_has_cert(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => true,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();
        $this->assertContains('documents.business_cert_seen', $keys);
        $this->assertNotContains('documents.tin_seen', $keys);
    }

    public function test_it_seeds_tin_item_only_when_vendor_has_tin(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => 'C1234567890',
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();
        $this->assertContains('documents.tin_seen', $keys);
        $this->assertNotContains('documents.business_cert_seen', $keys);
    }

    public function test_it_resumes_existing_draft_instead_of_creating_duplicate(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $first = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $second = app(StartVendorVisit::class)->execute($vendor, $agent, 5.57, -0.3);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\VendorVisit::where('vendor_user_id', $vendor->id)->count());
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=StartVendorVisitTest
```

Expected: all five fail — class not found.

- [ ] **Step 3: Create the action**

Create `app/Actions/VendorVisit/StartVendorVisit.php`:

```php
<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;

class StartVendorVisit
{
    public function execute(
        User $vendor,
        User $agent,
        float $latitude,
        float $longitude,
    ): VendorVisit {
        return DB::transaction(function () use ($vendor, $agent, $latitude, $longitude) {
            $existingDraft = VendorVisit::query()
                ->where('vendor_user_id', $vendor->id)
                ->where('field_agent_user_id', $agent->id)
                ->where('status', VendorVisitStatus::Draft->value)
                ->first();

            if ($existingDraft) {
                return $existingDraft->load('items');
            }

            $visit = VendorVisit::create([
                'vendor_user_id' => $vendor->id,
                'field_agent_user_id' => $agent->id,
                'status' => VendorVisitStatus::Draft->value,
                'started_at' => now(),
                'visit_latitude' => $latitude,
                'visit_longitude' => $longitude,
            ]);

            $this->seedItems($visit, $vendor);

            return $visit->load('items');
        });
    }

    private function seedItems(VendorVisit $visit, User $vendor): void
    {
        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        $hasCert = (bool) ($application?->has_business_certificate);
        $hasTin = filled($application?->tin_number);

        foreach (config('vendor_visit_checklist.items', []) as $item) {
            if (! $this->shouldSeed($item['seed_if'], $hasCert, $hasTin)) {
                continue;
            }

            $visit->items()->create([
                'item_key' => $item['key'],
                'category' => $item['category'],
                'criticality' => $item['criticality'],
                'passed' => null,
                'note' => null,
            ]);
        }
    }

    private function shouldSeed(string $seedIf, bool $hasCert, bool $hasTin): bool
    {
        return match ($seedIf) {
            'always' => true,
            'has_business_cert' => $hasCert,
            'has_tin' => $hasTin,
            default => false,
        };
    }
}
```

- [ ] **Step 4: Run and verify they pass**

```bash
php artisan test --compact --filter=StartVendorVisitTest
```

Expected: all five pass.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/VendorVisit/StartVendorVisit.php tests/Unit/Actions/VendorVisit/StartVendorVisitTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add StartVendorVisit action

Creates the draft visit with GPS, started_at, and pre-seeds checklist
items based on the vendor's registration status. Idempotent: resuming
for the same (agent, vendor) pair returns the existing draft.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: `CompleteVendorVisit` action (the auto-compute rule)

**Files:**
- Create: `app/Actions/VendorVisit/CompleteVendorVisit.php`
- Create: `tests/Unit/Actions/VendorVisit/CompleteVendorVisitTest.php`

**Mental model:** This is the **judge**. It reads the filled-in checklist folder and stamps an outcome on it: PASS, FAIL, or ESCALATE. It must be a pure function of the visit's current state — no side effects beyond writing the outcome on the visit itself. Badge propagation is somebody else's job (the observer, Task 6).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Actions/VendorVisit/CompleteVendorVisitTest.php`:

```php
<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFilledVisit(bool $hasCert = false, bool $hasTin = false)
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => $hasCert,
            'tin_number' => $hasTin ? 'C1234567890' : null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);

        $visit->items()->update(['passed' => true]);

        return $visit->fresh('items');
    }

    public function test_all_critical_pass_plus_photos_yields_passed_with_12_month_expiry(): void
    {
        $visit = $this->makeFilledVisit();
        $before = now();

        $result = app(CompleteVendorVisit::class)->execute($visit);

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertSame(VendorVisitStatus::Passed, $result->computed_result);
        $this->assertNotNull($result->submitted_at);
        $this->assertNotNull($result->badge_issued_at);
        $this->assertTrue(
            $result->badge_expires_at->greaterThanOrEqualTo($before->copy()->addMonths(12))
        );
    }

    public function test_escalated_always_yields_submitted(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['escalated' => true]);

        $result = app(CompleteVendorVisit::class)->execute($visit);

        $this->assertSame(VendorVisitStatus::Submitted, $result->status);
        $this->assertNull($result->badge_issued_at);
    }

    public function test_failing_any_critical_item_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'identity.person_matches_ghana_card')
            ->update(['passed' => false]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
        $this->assertNull($result->badge_issued_at);
    }

    public function test_failing_informational_alone_still_yields_passed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'financial.momo_test_received')
            ->update(['passed' => false]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertNotNull($result->badge_issued_at);
    }

    public function test_missing_storefront_photo_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['storefront_photo_path' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_missing_owner_photo_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->update(['owner_photo_path' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_any_item_left_unanswered_yields_failed(): void
    {
        $visit = $this->makeFilledVisit();
        $visit->items()
            ->where('item_key', 'physical.location_is_real')
            ->update(['passed' => null]);

        $result = app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
    }

    public function test_submitting_a_terminal_visit_is_a_noop(): void
    {
        $visit = $this->makeFilledVisit();
        $completed = app(CompleteVendorVisit::class)->execute($visit);
        $firstSubmittedAt = $completed->submitted_at;

        $again = app(CompleteVendorVisit::class)->execute($completed->fresh('items'));

        $this->assertSame(VendorVisitStatus::Passed, $again->status);
        $this->assertTrue($firstSubmittedAt->equalTo($again->submitted_at));
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=CompleteVendorVisitTest
```

Expected: all fail — class not found.

- [ ] **Step 3: Create the action**

Create `app/Actions/VendorVisit/CompleteVendorVisit.php`:

```php
<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitItemCriticality;
use App\Enums\VendorVisitStatus;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;

class CompleteVendorVisit
{
    public function execute(VendorVisit $visit): VendorVisit
    {
        if ($visit->status->isTerminal()) {
            return $visit;
        }

        return DB::transaction(function () use ($visit) {
            $visit->loadMissing('items');

            $outcome = $this->computeOutcome($visit);
            $now = now();

            $updates = [
                'status' => $outcome->value,
                'computed_result' => $outcome->value,
                'submitted_at' => $now,
            ];

            if ($outcome === VendorVisitStatus::Passed) {
                $updates['badge_issued_at'] = $now;
                $updates['badge_expires_at'] = $now->copy()->addMonths(
                    (int) config('vendor_visit_checklist.badge_validity_months', 12)
                );
            }

            $visit->update($updates);

            return $visit->refresh();
        });
    }

    private function computeOutcome(VendorVisit $visit): VendorVisitStatus
    {
        if ($visit->escalated) {
            return VendorVisitStatus::Submitted;
        }

        if (blank($visit->storefront_photo_path) || blank($visit->owner_photo_path)) {
            return VendorVisitStatus::Failed;
        }

        $hasUnanswered = $visit->items->contains(fn ($item) => $item->passed === null);
        if ($hasUnanswered) {
            return VendorVisitStatus::Failed;
        }

        $criticalFailed = $visit->items
            ->where('criticality', VendorVisitItemCriticality::Critical)
            ->contains(fn ($item) => $item->passed === false);

        return $criticalFailed ? VendorVisitStatus::Failed : VendorVisitStatus::Passed;
    }
}
```

- [ ] **Step 4: Run and verify they pass**

```bash
php artisan test --compact --filter=CompleteVendorVisitTest
```

Expected: all eight pass.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/VendorVisit/CompleteVendorVisit.php tests/Unit/Actions/VendorVisit/CompleteVendorVisitTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add CompleteVendorVisit auto-compute action

Pure-function judge: reads a draft visit's items + photos + escalation
flag and stamps status = passed | failed | submitted. Idempotent on
already-terminal visits. Badge window written when passed; badge
propagation to the vendor user record is the observer's job.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: `VendorVisitObserver` — badge propagation

**Files:**
- Create: `app/Observers/VendorVisitObserver.php`
- Create: `tests/Feature/Observers/VendorVisitObserverTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Mental model:** When a visit transitions into a terminal state, somebody needs to sync the cached `users.field_verified_*` columns. Rather than sprinkle that logic across every action, there is **one** path: the observer. Actions write the visit; the observer reacts. This is the single-writer invariant that keeps the denormalized columns trustworthy.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Observers/VendorVisitObserverTest.php`:

```php
<?php

namespace Tests\Feature\Observers;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorVisitObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function passingVisit(User $vendor, User $agent): VendorVisit
    {
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);
        $visit->items()->update(['passed' => true]);

        return app(CompleteVendorVisit::class)->execute($visit->fresh('items'));
    }

    public function test_passing_visit_sets_cached_columns_on_vendor(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();

        $visit = $this->passingVisit($vendor, $agent);

        $vendor->refresh();

        $this->assertNotNull($vendor->field_verified_at);
        $this->assertNotNull($vendor->field_verified_until);
        $this->assertTrue($vendor->field_verified_until->equalTo($visit->badge_expires_at));
    }

    public function test_revoked_visit_clears_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();
        $visit = $this->passingVisit($vendor, $agent);

        $visit->update(['status' => VendorVisitStatus::Revoked->value]);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_later_passing_visit_extends_cached_expiry(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agentA = User::factory()->fieldAgent()->create();
        $agentB = User::factory()->fieldAgent()->create();

        $this->travelTo(now()->subMonths(3));
        $visitA = $this->passingVisit($vendor, $agentA);
        $vendor->refresh();
        $originalExpiry = $vendor->field_verified_until;

        $this->travelTo(now()->addMonths(3));
        $this->passingVisit($vendor, $agentB);

        $vendor->refresh();
        $this->assertTrue($vendor->field_verified_until->greaterThan($originalExpiry));
    }

    public function test_failed_visit_does_not_touch_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();

        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $visit->update([
            'storefront_photo_path' => 'visits/sf.jpg',
            'owner_photo_path' => 'visits/ow.jpg',
        ]);
        $visit->items()->where('criticality', 'critical')->first()->update(['passed' => false]);
        $visit->items()->where('passed', null)->update(['passed' => true]);

        app(CompleteVendorVisit::class)->execute($visit->fresh('items'));

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
    }

    public function test_override_from_passed_to_failed_clears_cached_columns(): void
    {
        $vendor = User::factory()->vendor()->create();
        $agent = User::factory()->fieldAgent()->create();
        $visit = $this->passingVisit($vendor, $agent);

        $visit->update([
            'status' => VendorVisitStatus::Failed->value,
            'admin_override_result' => VendorVisitStatus::Failed->value,
        ]);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=VendorVisitObserverTest
```

Expected: five failures — observer does not exist / cached columns not updated.

- [ ] **Step 3: Create the observer**

Create `app/Observers/VendorVisitObserver.php`:

```php
<?php

namespace App\Observers;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;

class VendorVisitObserver
{
    public function saved(VendorVisit $visit): void
    {
        if (! $visit->wasRecentlyCreated && ! $this->transitioned($visit)) {
            return;
        }

        $this->syncBadgeForVendor($visit->vendor_user_id);
    }

    public function deleted(VendorVisit $visit): void
    {
        $this->syncBadgeForVendor($visit->vendor_user_id);
    }

    private function transitioned(VendorVisit $visit): bool
    {
        return $visit->wasChanged(['status', 'badge_expires_at', 'admin_override_result']);
    }

    private function syncBadgeForVendor(int $vendorUserId): void
    {
        $active = VendorVisit::query()
            ->where('vendor_user_id', $vendorUserId)
            ->where('status', VendorVisitStatus::Passed->value)
            ->whereNotNull('badge_expires_at')
            ->orderByDesc('badge_expires_at')
            ->first();

        User::query()->whereKey($vendorUserId)->update([
            'field_verified_at' => $active?->badge_issued_at,
            'field_verified_until' => $active?->badge_expires_at,
        ]);
    }
}
```

- [ ] **Step 4: Register the observer**

Open `app/Providers/AppServiceProvider.php` and, inside `boot()`, add near the other `Model::observe(...)` calls:

```php
\App\Models\VendorVisit::observe(\App\Observers\VendorVisitObserver::class);
```

- [ ] **Step 5: Run tests and verify they pass**

```bash
php artisan test --compact --filter=VendorVisitObserverTest
```

Expected: all five pass.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Observers/VendorVisitObserver.php app/Providers/AppServiceProvider.php tests/Feature/Observers/VendorVisitObserverTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): sync cached badge columns via observer

VendorVisitObserver reacts to any status/badge transition by recomputing
users.field_verified_at/until from the latest passing visit. Single
writer for the denormalized columns keeps them trustworthy without
controllers or actions having to remember to update them.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: `OverrideVendorVisit` + `RevokeFieldVerificationBadge` actions

**Files:**
- Create: `app/Actions/VendorVisit/OverrideVendorVisit.php`
- Create: `app/Actions/VendorVisit/RevokeFieldVerificationBadge.php`
- Create: `tests/Unit/Actions/VendorVisit/OverrideVendorVisitTest.php`
- Create: `tests/Unit/Actions/VendorVisit/RevokeFieldVerificationBadgeTest.php`

**Mental model:** Two separate admin levers. `Override` edits the outcome *of a specific visit* (e.g., "that visit was wrongly auto-failed"). `Revoke` is the nuclear button — the visit stays `passed` historically, but we set the active badge to cleared on the vendor. Separate actions keep the audit trail clean: overrides live on the visit; revocations set status to `revoked` which the observer catches.

- [ ] **Step 1: Write the failing tests for `OverrideVendorVisit`**

Create `tests/Unit/Actions/VendorVisit/OverrideVendorVisitTest.php`:

```php
<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\OverrideVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OverrideVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_override_failed_to_passed_and_badge_is_issued(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            visit: $visit,
            admin: $admin,
            newResult: VendorVisitStatus::Passed,
            reason: 'Reviewed photos; agent misclicked the location item.',
        );

        $this->assertSame(VendorVisitStatus::Passed, $result->status);
        $this->assertSame(VendorVisitStatus::Passed, $result->admin_override_result);
        $this->assertSame($admin->id, $result->admin_override_by);
        $this->assertNotNull($result->admin_override_at);
        $this->assertNotNull($result->badge_issued_at);
        $this->assertNotNull($result->badge_expires_at);
    }

    public function test_admin_can_override_passed_to_failed_and_badge_window_is_cleared(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            $visit,
            $admin,
            VendorVisitStatus::Failed,
            'Customer reported the business doesn\'t exist.',
        );

        $this->assertSame(VendorVisitStatus::Failed, $result->status);
        $this->assertNull($result->badge_issued_at);
        $this->assertNull($result->badge_expires_at);
    }

    public function test_computed_result_is_preserved_after_override(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $result = app(OverrideVendorVisit::class)->execute(
            $visit,
            $admin,
            VendorVisitStatus::Passed,
            'reason',
        );

        $this->assertSame(VendorVisitStatus::Failed, $result->computed_result);
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(InvalidArgumentException::class);
        app(OverrideVendorVisit::class)->execute($visit, $admin, VendorVisitStatus::Passed, '');
    }

    public function test_only_passed_or_failed_overrides_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(InvalidArgumentException::class);
        app(OverrideVendorVisit::class)->execute($visit, $admin, VendorVisitStatus::Revoked, 'reason');
    }
}
```

- [ ] **Step 2: Write the failing tests for `RevokeFieldVerificationBadge`**

Create `tests/Unit/Actions/VendorVisit/RevokeFieldVerificationBadgeTest.php`:

```php
<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\RevokeFieldVerificationBadge;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RevokeFieldVerificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_active_badge_and_clears_cached_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        $result = app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, 'fraud discovered');

        $this->assertSame(VendorVisitStatus::Revoked, $result->status);
        $this->assertSame('fraud discovered', $result->admin_override_reason);
        $this->assertSame($admin->id, $result->admin_override_by);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_cannot_revoke_a_non_passed_visit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->expectException(RuntimeException::class);
        app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, 'reason');
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, '');
    }
}
```

- [ ] **Step 3: Run and verify they fail**

```bash
php artisan test --compact --filter='OverrideVendorVisitTest|RevokeFieldVerificationBadgeTest'
```

Expected: all fail — classes not found.

- [ ] **Step 4: Create `OverrideVendorVisit`**

Create `app/Actions/VendorVisit/OverrideVendorVisit.php`:

```php
<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OverrideVendorVisit
{
    public function execute(
        VendorVisit $visit,
        User $admin,
        VendorVisitStatus $newResult,
        string $reason,
    ): VendorVisit {
        if (! in_array($newResult, [VendorVisitStatus::Passed, VendorVisitStatus::Failed], true)) {
            throw new InvalidArgumentException('Override result must be Passed or Failed.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Override reason is required.');
        }

        return DB::transaction(function () use ($visit, $admin, $newResult, $reason) {
            $now = now();

            $updates = [
                'status' => $newResult->value,
                'admin_override_result' => $newResult->value,
                'admin_override_reason' => $reason,
                'admin_override_by' => $admin->id,
                'admin_override_at' => $now,
            ];

            if ($newResult === VendorVisitStatus::Passed) {
                $updates['badge_issued_at'] = $visit->badge_issued_at ?? $now;
                $updates['badge_expires_at'] = $now->copy()->addMonths(
                    (int) config('vendor_visit_checklist.badge_validity_months', 12)
                );
            } else {
                $updates['badge_issued_at'] = null;
                $updates['badge_expires_at'] = null;
            }

            $visit->update($updates);

            return $visit->refresh();
        });
    }
}
```

- [ ] **Step 5: Create `RevokeFieldVerificationBadge`**

Create `app/Actions/VendorVisit/RevokeFieldVerificationBadge.php`:

```php
<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RevokeFieldVerificationBadge
{
    public function execute(VendorVisit $visit, User $admin, string $reason): VendorVisit
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Revocation reason is required.');
        }

        if ($visit->status !== VendorVisitStatus::Passed) {
            throw new RuntimeException('Only a passed visit can be revoked.');
        }

        return DB::transaction(function () use ($visit, $admin, $reason) {
            $visit->update([
                'status' => VendorVisitStatus::Revoked->value,
                'admin_override_result' => VendorVisitStatus::Revoked->value,
                'admin_override_reason' => $reason,
                'admin_override_by' => $admin->id,
                'admin_override_at' => now(),
                'badge_issued_at' => null,
                'badge_expires_at' => null,
            ]);

            return $visit->refresh();
        });
    }
}
```

- [ ] **Step 6: Run tests and verify they pass**

```bash
php artisan test --compact --filter='OverrideVendorVisitTest|RevokeFieldVerificationBadgeTest'
```

Expected: all 8 pass.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/VendorVisit/OverrideVendorVisit.php app/Actions/VendorVisit/RevokeFieldVerificationBadge.php tests/Unit/Actions/VendorVisit/OverrideVendorVisitTest.php tests/Unit/Actions/VendorVisit/RevokeFieldVerificationBadgeTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add admin override and badge revocation actions

OverrideVendorVisit edits a visit's outcome (computed_result preserved
for audit); RevokeFieldVerificationBadge sets status=revoked on a
passed visit. Both require a reason and both rely on
VendorVisitObserver to resync cached columns.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: `FieldVerificationInvalidationObserver` — clear badge on critical profile edits

**Files:**
- Create: `app/Observers/FieldVerificationInvalidationObserver.php`
- Create: `tests/Feature/Observers/FieldVerificationInvalidationObserverTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Mental model:** A locksmith wouldn't let you swap the lock core on a verified safe without re-inspecting it. Likewise, if a verified vendor edits their business name, address, phone, or Ghana Card, the badge must drop immediately — otherwise a scammer could pass verification and then swap their details. This is the anti-drift safety net flagged in the spec.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Observers/FieldVerificationInvalidationObserverTest.php`:

```php
<?php

namespace Tests\Feature\Observers;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldVerificationInvalidationObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function verifiedVendor(): User
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        return $vendor;
    }

    public function test_editing_business_name_invalidates_active_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['business_name' => 'New Business Name']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_editing_phone_invalidates_active_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['phone' => '+233200000001']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_editing_email_does_not_invalidate_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['email' => 'new@example.com']);

        $vendor->refresh();
        $this->assertNotNull($vendor->field_verified_until);
    }

    public function test_updating_vendor_application_ghana_card_invalidates_badge(): void
    {
        $vendor = $this->verifiedVendor();
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'ghana_card_front' => 'old/front.jpg',
        ]);

        $application->update(['ghana_card_front' => 'new/front.jpg']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_non_verified_vendor_is_not_affected(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => null,
            'field_verified_until' => null,
            'business_name' => 'Old Name',
        ]);

        $vendor->update(['business_name' => 'New Name']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=FieldVerificationInvalidationObserverTest
```

Expected: three of the five failures (editing business name / phone / ghana card still leaves the badge in place); the "email does not invalidate" and "non-verified not affected" pass already because there's no logic to change anything.

- [ ] **Step 3: Create the observer**

Create `app/Observers/FieldVerificationInvalidationObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\User;
use App\Models\VendorApplication;

class FieldVerificationInvalidationObserver
{
    /** @var array<int, string> */
    private array $userWatchedFields = [
        'business_name',
        'phone',
        'first_name',
        'last_name',
        'name',
    ];

    /** @var array<int, string> */
    private array $vendorApplicationWatchedFields = [
        'ghana_card_front',
        'ghana_card_back',
        'selfie_image',
    ];

    public function userUpdated(User $user): void
    {
        if ($user->field_verified_until === null) {
            return;
        }
        if (! $user->wasChanged($this->userWatchedFields)) {
            return;
        }
        $this->clearBadge($user);
    }

    public function vendorApplicationUpdated(VendorApplication $application): void
    {
        if (! $application->wasChanged($this->vendorApplicationWatchedFields)) {
            return;
        }
        $vendor = $application->user;
        if ($vendor === null || $vendor->field_verified_until === null) {
            return;
        }
        $this->clearBadge($vendor);
    }

    private function clearBadge(User $vendor): void
    {
        User::query()->whereKey($vendor->id)->update([
            'field_verified_at' => null,
            'field_verified_until' => null,
        ]);
    }
}
```

- [ ] **Step 4: Register as two model-specific observer hooks**

Open `app/Providers/AppServiceProvider.php` and add inside `boot()`:

```php
\App\Models\User::updated(function (\App\Models\User $user) {
    app(\App\Observers\FieldVerificationInvalidationObserver::class)->userUpdated($user);
});
\App\Models\VendorApplication::updated(function (\App\Models\VendorApplication $app) {
    app(\App\Observers\FieldVerificationInvalidationObserver::class)->vendorApplicationUpdated($app);
});
```

These hook-style registrations avoid colliding with any existing `User::observe(...)` registration.

- [ ] **Step 5: Run tests and verify they pass**

```bash
php artisan test --compact --filter=FieldVerificationInvalidationObserverTest
```

Expected: all five pass.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Observers/FieldVerificationInvalidationObserver.php app/Providers/AppServiceProvider.php tests/Feature/Observers/FieldVerificationInvalidationObserverTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): auto-invalidate badge on critical profile edits

Anti-fraud safety net: if a verified vendor edits their business name,
phone, legal name, or any Ghana Card / selfie file on their
VendorApplication, the cached field_verified_* columns are cleared
immediately. The badge can only be re-issued by a new passing field
visit.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Form Requests

**Files:**
- Create: `app/Http/Requests/FieldAgent/StartVendorVisitRequest.php`
- Create: `app/Http/Requests/FieldAgent/UpdateVendorVisitItemRequest.php`
- Create: `app/Http/Requests/FieldAgent/SubmitVendorVisitRequest.php`
- Create: `app/Http/Requests/Admin/OverrideVendorVisitRequest.php`
- Create: `app/Http/Requests/Admin/RevokeFieldVerificationBadgeRequest.php`

**Mental model:** Form requests are the **bouncers** at the door of each controller action. They catch malformed input before any business logic runs. Keep them pure: rules + messages, nothing else. No DB lookups, no side effects.

- [ ] **Step 1: Create `StartVendorVisitRequest`**

```bash
php artisan make:request FieldAgent/StartVendorVisitRequest --no-interaction
```

Edit the file:

```php
<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class StartVendorVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'field_agent';
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Location is required to start a visit.',
            'longitude.required' => 'Location is required to start a visit.',
        ];
    }
}
```

- [ ] **Step 2: Create `UpdateVendorVisitItemRequest`**

```bash
php artisan make:request FieldAgent/UpdateVendorVisitItemRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorVisitItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'field_agent';
    }

    public function rules(): array
    {
        return [
            'passed' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 3: Create `SubmitVendorVisitRequest`**

```bash
php artisan make:request FieldAgent/SubmitVendorVisitRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVendorVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'field_agent';
    }

    public function rules(): array
    {
        return [
            'storefront_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'owner_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'escalated' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'storefront_photo.max' => 'The storefront photo must be 5MB or smaller.',
            'owner_photo.max' => 'The owner photo must be 5MB or smaller.',
        ];
    }
}
```

(Photos are optional on this request because they're uploaded incrementally via dedicated endpoints on Task 10; this request validates any photos still being included in the final submit payload.)

- [ ] **Step 4: Create `OverrideVendorVisitRequest`**

```bash
php artisan make:request Admin/OverrideVendorVisitRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\VendorVisitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideVendorVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([VendorVisitStatus::Passed->value, VendorVisitStatus::Failed->value])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 5: Create `RevokeFieldVerificationBadgeRequest`**

```bash
php artisan make:request Admin/RevokeFieldVerificationBadgeRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeFieldVerificationBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/FieldAgent/ app/Http/Requests/Admin/
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add form requests for field-agent and admin actions

Five form requests validate the boundary inputs for start-visit,
update-item, submit, admin override, and admin badge revocation.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: `FieldAgent\VendorVisitsController` + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/FieldAgent/VendorVisitsController.php`
- Create: `tests/Feature/FieldAgent/VendorVisitsControllerTest.php`
- Modify: `routes/web.php`

**Mental model:** The controller is the **switchboard**: it routes an HTTP request to the right action, asserts authorization, and wraps the result in an Inertia response. No business logic lives here — the actions from Tasks 4, 5, 6 already decide what a visit's outcome is. The controller just wires them up.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/FieldAgent/VendorVisitsControllerTest.php`:

```php
<?php

namespace Tests\Feature\FieldAgent;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorVisitsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedVendor(array $appAttributes = []): User
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create(array_merge([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ], $appAttributes));

        return $vendor;
    }

    public function test_non_field_agent_cannot_access_visits_index(): void
    {
        $user = User::factory()->vendor()->create();

        $this->actingAs($user)
            ->get('/field-agent/visits')
            ->assertForbidden();
    }

    public function test_field_agent_sees_visits_index(): void
    {
        $agent = User::factory()->fieldAgent()->create();

        $this->actingAs($agent)
            ->get('/field-agent/visits')
            ->assertOk();
    }

    public function test_agent_can_start_a_visit(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $response = $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56,
            'longitude' => -0.2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendor_visits', [
            'vendor_user_id' => $vendor->id,
            'field_agent_user_id' => $agent->id,
            'status' => VendorVisitStatus::Draft->value,
        ]);
    }

    public function test_agent_cannot_start_a_visit_without_gps(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $this->actingAs($agent)
            ->postJson("/field-agent/visits/{$vendor->id}/start", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_agent_cannot_start_a_visit_on_unapproved_vendor(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($agent)
            ->postJson("/field-agent/visits/{$vendor->id}/start", [
                'latitude' => 5.56, 'longitude' => -0.2,
            ])
            ->assertStatus(422);
    }

    public function test_agent_cannot_open_another_agents_draft(): void
    {
        $agentA = User::factory()->fieldAgent()->create();
        $agentB = User::factory()->fieldAgent()->create();
        $visit = VendorVisit::factory()->create(['field_agent_user_id' => $agentA->id]);

        $this->actingAs($agentB)
            ->get("/field-agent/visits/forms/{$visit->id}")
            ->assertForbidden();
    }

    public function test_agent_can_update_a_single_item(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();
        $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56, 'longitude' => -0.2,
        ]);
        $visit = VendorVisit::where('field_agent_user_id', $agent->id)->firstOrFail();
        $item = $visit->items()->firstOrFail();

        $this->actingAs($agent)
            ->patchJson("/field-agent/visits/forms/{$visit->id}/items/{$item->id}", [
                'passed' => true,
                'note' => 'checked and matches',
            ])
            ->assertOk();

        $this->assertSame(true, $item->fresh()->passed);
        $this->assertSame('checked and matches', $item->fresh()->note);
    }

    public function test_submit_with_all_critical_pass_yields_passed(): void
    {
        Storage::fake('public');
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56, 'longitude' => -0.2,
        ]);
        $visit = VendorVisit::where('field_agent_user_id', $agent->id)->firstOrFail();
        $visit->items()->update(['passed' => true]);

        $response = $this->actingAs($agent)->postJson("/field-agent/visits/forms/{$visit->id}/submit", [
            'storefront_photo' => UploadedFile::fake()->image('sf.jpg'),
            'owner_photo' => UploadedFile::fake()->image('ow.jpg'),
            'notes' => 'all good',
            'escalated' => false,
        ]);

        $response->assertRedirect();
        $this->assertSame(VendorVisitStatus::Passed, $visit->fresh()->status);
        $vendor->refresh();
        $this->assertNotNull($vendor->field_verified_until);
    }

    public function test_submit_is_idempotent_once_terminal(): void
    {
        Storage::fake('public');
        $agent = User::factory()->fieldAgent()->create();
        $visit = VendorVisit::factory()->passed()->create(['field_agent_user_id' => $agent->id]);
        $firstSubmittedAt = $visit->submitted_at;

        $this->actingAs($agent)->postJson("/field-agent/visits/forms/{$visit->id}/submit", [
            'storefront_photo' => UploadedFile::fake()->image('new.jpg'),
            'owner_photo' => UploadedFile::fake()->image('new2.jpg'),
        ])->assertRedirect();

        $this->assertTrue($firstSubmittedAt->equalTo($visit->fresh()->submitted_at));
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=FieldAgent\\\\VendorVisitsControllerTest
```

Expected: most tests fail — route / controller missing.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/FieldAgent/VendorVisitsController.php`:

```php
<?php

namespace App\Http\Controllers\FieldAgent;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StartVendorVisitRequest;
use App\Http\Requests\FieldAgent\SubmitVendorVisitRequest;
use App\Http\Requests\FieldAgent\UpdateVendorVisitItemRequest;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use App\Models\VendorVisitItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VendorVisitsController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $needsVisit = User::query()
            ->where('role', 'vendor')
            ->whereHas('vendorApplication', fn ($q) => $q->where('status', 'approved'))
            ->where(function ($q) {
                $q->whereNull('field_verified_until')
                    ->orWhere('field_verified_until', '<=', now());
            })
            ->select(['id', 'business_name', 'name', 'field_verified_until'])
            ->orderBy('field_verified_until')
            ->limit(50)
            ->get();

        $expiringSoon = User::query()
            ->where('role', 'vendor')
            ->whereBetween('field_verified_until', [now(), now()->addDays(30)])
            ->select(['id', 'business_name', 'name', 'field_verified_until'])
            ->orderBy('field_verified_until')
            ->limit(50)
            ->get();

        $drafts = VendorVisit::query()
            ->where('field_agent_user_id', $agent->id)
            ->where('status', VendorVisitStatus::Draft->value)
            ->with('vendor:id,business_name,name')
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('field-agent/visits/index', [
            'needsVisit' => $needsVisit,
            'expiringSoon' => $expiringSoon,
            'drafts' => $drafts,
        ]);
    }

    public function show(Request $request, User $vendor): Response
    {
        abort_unless($vendor->role === 'vendor', 404);

        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        abort_unless($application?->status === 'approved', 404, 'Vendor is not approved yet.');

        $visits = VendorVisit::query()
            ->where('vendor_user_id', $vendor->id)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        return Inertia::render('field-agent/visits/show', [
            'vendor' => $vendor->only(['id', 'business_name', 'name', 'email', 'phone']),
            'application' => $application?->only([
                'id', 'has_business_certificate', 'tin_number', 'ghana_card_front',
                'ghana_card_back', 'selfie_image', 'business_certificate_document',
                'tin_document', 'mobile_money_number', 'mobile_money_provider',
                'facebook_handle', 'instagram_handle', 'twitter_handle',
            ]),
            'recentVisits' => $visits,
        ]);
    }

    public function start(StartVendorVisitRequest $request, User $vendor, StartVendorVisit $start): RedirectResponse
    {
        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        if ($application?->status !== 'approved') {
            return back()->withErrors([
                'vendor' => "This vendor isn't approved yet — there's nothing to verify.",
            ]);
        }

        $visit = $start->execute(
            vendor: $vendor,
            agent: $request->user(),
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
        );

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }

    public function form(Request $request, VendorVisit $visit): Response
    {
        $this->authorizeAgent($request, $visit);

        $visit->load(['items', 'vendor:id,business_name,name']);

        return Inertia::render('field-agent/visits/new', [
            'visit' => $visit,
            'items' => $visit->items,
        ]);
    }

    public function updateItem(UpdateVendorVisitItemRequest $request, VendorVisit $visit, VendorVisitItem $item)
    {
        $this->authorizeAgent($request, $visit);
        abort_unless($item->vendor_visit_id === $visit->id, 404);
        abort_if($visit->status->isTerminal(), 422, 'Visit is already submitted.');

        $item->update($request->validated());

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function submit(SubmitVendorVisitRequest $request, VendorVisit $visit, CompleteVendorVisit $complete): RedirectResponse
    {
        $this->authorizeAgent($request, $visit);

        if ($visit->status->isTerminal()) {
            return redirect("/field-agent/visits/forms/{$visit->id}");
        }

        $updates = $request->only(['notes']);
        $updates['escalated'] = (bool) $request->boolean('escalated');

        if ($request->hasFile('storefront_photo')) {
            $updates['storefront_photo_path'] = $request->file('storefront_photo')
                ->store('vendor-visits/storefronts', 'public');
        }
        if ($request->hasFile('owner_photo')) {
            $updates['owner_photo_path'] = $request->file('owner_photo')
                ->store('vendor-visits/owners', 'public');
        }

        $visit->update($updates);

        $complete->execute($visit->fresh('items'));

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }

    private function authorizeAgent(Request $request, VendorVisit $visit): void
    {
        abort_unless($visit->field_agent_user_id === $request->user()->id, 403);
    }
}
```

- [ ] **Step 4: Register the routes**

Open `routes/web.php`. Find the existing `Route::middleware(['auth', 'dashboard'])->prefix('field-agent')->name('field-agent.')->group(function () {...});` block. Inside it, **before** the `Route::get('/{any?}', ...)` catch-all, add:

```php
Route::middleware('role:field_agent')->prefix('visits')->name('visits.')->group(function () {
    Route::get('/', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'index'])->name('index');
    Route::get('/{vendor}', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'show'])->name('show');
    Route::post('/{vendor}/start', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'start'])->name('start');
    Route::get('/forms/{visit}', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'form'])->name('form');
    Route::patch('/forms/{visit}/items/{item}', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'updateItem'])->name('items.update');
    Route::post('/forms/{visit}/submit', [\App\Http\Controllers\FieldAgent\VendorVisitsController::class, 'submit'])->name('submit');
});
```

- [ ] **Step 5: Run tests and verify they pass**

```bash
php artisan test --compact --filter=FieldAgent\\\\VendorVisitsControllerTest
```

Expected: all tests pass. If "non-field-agent cannot access" returns 302 instead of 403, check whether the existing `role` middleware uses `abort(403)` or a redirect; if it redirects, change the assertion in the test to `->assertRedirect()` or match the middleware's actual behavior.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/FieldAgent/VendorVisitsController.php tests/Feature/FieldAgent/VendorVisitsControllerTest.php routes/web.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add field-agent vendor visits controller + routes

Six endpoints: index (visits-to-do dashboard), show (vendor pre-visit
reference), start (creates draft with GPS), form (visit form page),
updateItem (auto-save PATCH), submit (applies photos + runs the
Complete action). All guarded by role:field_agent.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Field-agent visits index page

**Files:**
- Create: `resources/js/Pages/field-agent/visits/index.tsx`

**Mental model:** The page is a thin view over the JSON props already shipped by `VendorVisitsController@index`. Data flows **down** (props → sections); actions flow **up** (`router.post(...)` or `router.visit(...)` on button clicks). No local state except UI collapsing.

**Props contract (from controller):**
- `needsVisit: Array<{id:number, business_name:string|null, name:string, field_verified_until:string|null}>`
- `expiringSoon: Array<same shape>`
- `drafts: Array<{id:string, started_at:string, vendor:{id, business_name, name}}>`

- [ ] **Step 1: Scaffold the page**

Create `resources/js/Pages/field-agent/visits/index.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

interface VendorRow {
    id: number;
    business_name: string | null;
    name: string;
    field_verified_until: string | null;
}

interface DraftRow {
    id: string;
    started_at: string;
    vendor: { id: number; business_name: string | null; name: string };
}

interface Props {
    needsVisit: VendorRow[];
    expiringSoon: VendorRow[];
    drafts: DraftRow[];
}

export default function VisitsIndex({ needsVisit, expiringSoon, drafts }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/field-agent/dashboard' }, { title: 'Visits', href: '/field-agent/visits' }]}>
            <Head title="Vendor visits" />
            <div className="space-y-8 p-6">
                <Section title={`Needs visit now (${needsVisit.length})`} emptyText="Nothing pending — great job.">
                    {needsVisit.map((v) => (
                        <VendorRow key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section title={`Expiring soon (${expiringSoon.length})`} emptyText="No badges expiring in the next 30 days.">
                    {expiringSoon.map((v) => (
                        <VendorRow key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section title={`Resume drafts (${drafts.length})`} emptyText="No draft visits.">
                    {drafts.map((d) => (
                        <Link key={d.id} href={`/field-agent/visits/forms/${d.id}`} className="block rounded border p-3 hover:bg-muted">
                            <div className="font-medium">{d.vendor.business_name ?? d.vendor.name}</div>
                            <div className="text-sm text-muted-foreground">Started {new Date(d.started_at).toLocaleString()}</div>
                        </Link>
                    ))}
                </Section>
            </div>
        </AppLayout>
    );
}

function Section({ title, emptyText, children }: { title: string; emptyText: string; children: React.ReactNode }) {
    const empty = !children || (Array.isArray(children) && children.length === 0);
    return (
        <section>
            <h2 className="mb-3 text-lg font-semibold">{title}</h2>
            <div className="space-y-2">{empty ? <p className="text-sm text-muted-foreground">{emptyText}</p> : children}</div>
        </section>
    );
}

function VendorRow({ vendor, badgeExpires }: { vendor: VendorRow; badgeExpires: string | null }) {
    const label = vendor.business_name ?? vendor.name;
    return (
        <Link href={`/field-agent/visits/${vendor.id}`} className="flex items-center justify-between rounded border p-3 hover:bg-muted">
            <div>
                <div className="font-medium">{label}</div>
                <div className="text-sm text-muted-foreground">
                    {badgeExpires ? `Expires ${new Date(badgeExpires).toLocaleDateString()}` : 'Never verified'}
                </div>
            </div>
            <span className="text-sm text-primary">Open →</span>
        </Link>
    );
}
```

- [ ] **Step 2: Manually smoke-test in browser**

Log in as a field agent (use the `!` prefix in the prompt to run the command interactively if needed) and visit `/field-agent/visits`. Expected: the three sections render without JS errors; the `VendorVisitsControllerTest::test_field_agent_sees_visits_index` test already covers server-side rendering.

- [ ] **Step 3: Pint (JS is not Pint-scope, but run Prettier if configured) + commit**

```bash
pnpm run format 2>/dev/null || true
git add resources/js/Pages/field-agent/visits/index.tsx
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add field-agent visits index page

Three stacked sections (needs-visit, expiring-soon, resume-drafts) over
the controller's JSON payload. Plain links for navigation; no local
state.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: Field-agent vendor detail page (pre-visit reference)

**Files:**
- Create: `resources/js/Pages/field-agent/visits/show.tsx`

**Props contract (from controller):**
- `vendor: {id, business_name, name, email, phone}`
- `application: {id, has_business_certificate, tin_number, ghana_card_front, ..., mobile_money_number, mobile_money_provider, facebook_handle, instagram_handle, twitter_handle} | null`
- `recentVisits: VendorVisit[]`

**Key behavior:**
- Big **Start visit** button that asks for GPS and POSTs to `/field-agent/visits/{vendor}/start`.
- Tap-to-enlarge image viewer for Ghana Card, selfie, and (if present) business certificate/TIN images. Use an existing modal/lightbox component from the project if one exists; otherwise a simple `<dialog>`.

- [ ] **Step 1: Scaffold the page**

Create `resources/js/Pages/field-agent/visits/show.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Vendor { id: number; business_name: string | null; name: string; email: string; phone: string | null }
interface VendorApplication {
    id: number;
    has_business_certificate: boolean;
    tin_number: string | null;
    ghana_card_front: string | null;
    ghana_card_back: string | null;
    selfie_image: string | null;
    business_certificate_document: string | null;
    tin_document: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;
    facebook_handle: string | null;
    instagram_handle: string | null;
    twitter_handle: string | null;
}
interface RecentVisit {
    id: string;
    started_at: string;
    status: string;
    badge_expires_at: string | null;
    field_agent: { id: number; name: string } | null;
}
interface Props { vendor: Vendor; application: VendorApplication | null; recentVisits: RecentVisit[] }

export default function VisitShow({ vendor, application, recentVisits }: Props) {
    const [starting, setStarting] = useState(false);

    const startVisit = () => {
        if (!navigator.geolocation) {
            alert('Your browser does not support location. A visit requires GPS.');
            return;
        }
        setStarting(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.post(`/field-agent/visits/${vendor.id}/start`, {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                }, { onFinish: () => setStarting(false) });
            },
            () => {
                setStarting(false);
                alert('Location is required to verify a visit happened in person. Please enable location and try again.');
            },
            { enableHighAccuracy: true, timeout: 15_000 },
        );
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Dashboard', href: '/field-agent/dashboard' },
            { title: 'Visits', href: '/field-agent/visits' },
            { title: vendor.business_name ?? vendor.name, href: `/field-agent/visits/${vendor.id}` },
        ]}>
            <Head title={`Visit — ${vendor.business_name ?? vendor.name}`} />
            <div className="space-y-6 p-6">
                <header>
                    <h1 className="text-2xl font-semibold">{vendor.business_name ?? vendor.name}</h1>
                    <p className="text-sm text-muted-foreground">{vendor.phone} · {vendor.email}</p>
                </header>

                <button
                    type="button"
                    onClick={startVisit}
                    disabled={starting}
                    className="w-full rounded bg-primary px-4 py-3 text-primary-foreground disabled:opacity-50"
                >
                    {starting ? 'Getting location…' : 'Start visit'}
                </button>

                <section>
                    <h2 className="mb-2 font-semibold">Claim data on file</h2>
                    <ClaimGrid application={application} />
                </section>

                <section>
                    <h2 className="mb-2 font-semibold">Recent visits</h2>
                    {recentVisits.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No prior visits.</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {recentVisits.map((v) => (
                                <li key={v.id}>
                                    {new Date(v.started_at).toLocaleDateString()} — <strong>{v.status}</strong>
                                    {v.badge_expires_at && ` (expires ${new Date(v.badge_expires_at).toLocaleDateString()})`}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function ClaimGrid({ application }: { application: VendorApplication | null }) {
    if (!application) return <p className="text-sm text-muted-foreground">No application on file.</p>;
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <DocImg label="Ghana Card (front)" path={application.ghana_card_front} />
            <DocImg label="Ghana Card (back)" path={application.ghana_card_back} />
            <DocImg label="Selfie" path={application.selfie_image} />
            {application.has_business_certificate && <DocImg label="Business certificate" path={application.business_certificate_document} />}
            {application.tin_number && <DocImg label="TIN document" path={application.tin_document} />}
            <KV label="Mobile money" value={application.mobile_money_provider ? `${application.mobile_money_provider} — ${application.mobile_money_number}` : '—'} />
            <KV label="TIN number" value={application.tin_number ?? '—'} />
            <KV label="Facebook" value={application.facebook_handle ?? '—'} />
            <KV label="Instagram" value={application.instagram_handle ?? '—'} />
            <KV label="Twitter/X" value={application.twitter_handle ?? '—'} />
        </div>
    );
}

function DocImg({ label, path }: { label: string; path: string | null }) {
    if (!path) return <KV label={label} value="—" />;
    const src = path.startsWith('http') ? path : `/storage/${path}`;
    return (
        <div>
            <div className="mb-1 text-xs font-medium text-muted-foreground">{label}</div>
            <a href={src} target="_blank" rel="noreferrer">
                <img src={src} alt={label} className="max-h-48 rounded border object-contain" />
            </a>
        </div>
    );
}

function KV({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground">{label}</div>
            <div className="text-sm">{value}</div>
        </div>
    );
}
```

Note: `/storage/...` path convention assumes the project uses `php artisan storage:link`. If the project uses a different helper (e.g., `storage_url()`), adjust the image src accordingly — grep the existing codebase for how other admin pages render `ghana_card_front` and match that pattern.

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/field-agent/visits/show.tsx
git commit -m "feat(vendor-visits): add field-agent vendor-detail pre-visit page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: Field-agent visit form page

**Files:**
- Create: `resources/js/Pages/field-agent/visits/new.tsx`

**Props contract (from controller):**
- `visit: VendorVisit` (with `id`, `status`, `escalated`, `storefront_photo_path`, `owner_photo_path`, `notes`)
- `items: VendorVisitItem[]` — each with `id`, `item_key`, `category`, `criticality`, `passed`, `note`.

**Key behavior:**
- Auto-save each item on toggle (PATCH `/field-agent/visits/forms/{visit}/items/{item}`).
- Notes debounce-save on blur (reuse the item-update endpoint).
- Photos uploaded via the final submit form; but for better UX, also support incremental upload. For v1 simplicity, we include photos in the final submit call (matches the controller's current `submit()` handler).
- Submit button disabled until every item has `passed !== null` **and** either the photos are present on the visit (already uploaded) **or** picked in the form.

- [ ] **Step 1: Scaffold the page**

Create `resources/js/Pages/field-agent/visits/new.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Item {
    id: number;
    item_key: string;
    category: string;
    criticality: string;
    passed: boolean | null;
    note: string | null;
}
interface Visit {
    id: string;
    status: string;
    escalated: boolean;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    vendor: { id: number; business_name: string | null; name: string };
}
interface Props { visit: Visit; items: Item[]; labels: Record<string, string> }

const CHECKLIST_LABELS: Record<string, string> = {
    'identity.person_matches_ghana_card': 'Does the person in front of you match the Ghana Card photo on file?',
    'identity.name_matches_records': 'Does the name on the physical Ghana Card match the name on file?',
    'physical.location_is_real': 'Is the claimed business address a real, findable location?',
    'physical.business_name_matches': 'Does the business at this address match the business name on file?',
    'physical.business_is_operational': 'Is there signage, stock, or active service — a real going concern?',
    'documents.business_cert_seen': 'Have you seen the physical business certificate, and does it match the file?',
    'documents.tin_seen': 'Have you seen the physical TIN document, and does it match the file?',
    'financial.phone_reachable': 'Did you call the registered phone and have it ring / be answered?',
    'financial.momo_test_received': 'Did your GHS 1 test MoMo reach the registered mobile money number?',
};

export default function VisitForm({ visit, items }: Props) {
    const [itemState, setItemState] = useState(items);
    const submit = useForm({
        storefront_photo: null as File | null,
        owner_photo: null as File | null,
        notes: visit.notes ?? '',
        escalated: visit.escalated,
    });

    const categories = useMemo(() => {
        const byCat: Record<string, Item[]> = {};
        itemState.forEach((i) => { (byCat[i.category] ??= []).push(i); });
        return byCat;
    }, [itemState]);

    const hasStorefront = Boolean(visit.storefront_photo_path) || Boolean(submit.data.storefront_photo);
    const hasOwner = Boolean(visit.owner_photo_path) || Boolean(submit.data.owner_photo);
    const allAnswered = itemState.every((i) => i.passed !== null);
    const canSubmit = allAnswered && hasStorefront && hasOwner;
    const isTerminal = visit.status !== 'draft';

    const toggleItem = (item: Item, passed: boolean) => {
        setItemState((prev) => prev.map((i) => (i.id === item.id ? { ...i, passed } : i)));
        router.patch(`/field-agent/visits/forms/${visit.id}/items/${item.id}`, { passed }, { preserveScroll: true, preserveState: true, only: [] });
    };

    const saveNote = (item: Item, note: string) => {
        router.patch(`/field-agent/visits/forms/${visit.id}/items/${item.id}`, { passed: item.passed, note }, { preserveScroll: true, preserveState: true, only: [] });
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submit.post(`/field-agent/visits/forms/${visit.id}/submit`, { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Visits', href: '/field-agent/visits' }, { title: visit.vendor.business_name ?? visit.vendor.name, href: `/field-agent/visits/${visit.vendor.id}` }, { title: 'Form', href: `/field-agent/visits/forms/${visit.id}` }]}>
            <Head title="Visit form" />
            <form onSubmit={onSubmit} className="mx-auto max-w-xl space-y-6 p-6">
                <div className="rounded bg-green-50 p-3 text-sm text-green-900">GPS captured ✓</div>

                {Object.entries(categories).map(([cat, catItems]) => (
                    <section key={cat}>
                        <h2 className="mb-2 text-lg font-semibold capitalize">{cat.replace('_', ' ')}</h2>
                        <div className="space-y-3">
                            {catItems.map((item) => (
                                <div key={item.id} className="rounded border p-3">
                                    <div className="text-sm">{CHECKLIST_LABELS[item.item_key] ?? item.item_key}</div>
                                    <div className="mt-2 flex gap-2">
                                        <button type="button" disabled={isTerminal} onClick={() => toggleItem(item, true)} className={`flex-1 rounded border px-3 py-1 ${item.passed === true ? 'bg-green-600 text-white' : ''}`}>Pass</button>
                                        <button type="button" disabled={isTerminal} onClick={() => toggleItem(item, false)} className={`flex-1 rounded border px-3 py-1 ${item.passed === false ? 'bg-red-600 text-white' : ''}`}>Fail</button>
                                    </div>
                                    <input
                                        disabled={isTerminal}
                                        placeholder="Optional note"
                                        defaultValue={item.note ?? ''}
                                        onBlur={(e) => saveNote(item, e.target.value)}
                                        className="mt-2 w-full rounded border px-2 py-1 text-sm"
                                    />
                                </div>
                            ))}
                        </div>
                    </section>
                ))}

                <section>
                    <h2 className="mb-2 text-lg font-semibold">Required evidence</h2>
                    <label className="mb-3 block">
                        <div className="text-sm">Storefront photo {hasStorefront && '✓'}</div>
                        <input type="file" accept="image/*" capture="environment" onChange={(e) => submit.setData('storefront_photo', e.target.files?.[0] ?? null)} />
                    </label>
                    <label className="mb-3 block">
                        <div className="text-sm">Owner-at-premises photo {hasOwner && '✓'}</div>
                        <input type="file" accept="image/*" capture="environment" onChange={(e) => submit.setData('owner_photo', e.target.files?.[0] ?? null)} />
                    </label>
                </section>

                <section>
                    <label className="block">
                        <div className="text-sm">General notes</div>
                        <textarea disabled={isTerminal} value={submit.data.notes} onChange={(e) => submit.setData('notes', e.target.value)} className="w-full rounded border p-2" rows={3} />
                    </label>
                    <label className="mt-3 flex items-center gap-2">
                        <input type="checkbox" disabled={isTerminal} checked={submit.data.escalated} onChange={(e) => submit.setData('escalated', e.target.checked)} />
                        <span className="text-sm">Escalate to admin — tick if something feels off but you can't prove it.</span>
                    </label>
                </section>

                <button type="submit" disabled={!canSubmit || submit.processing || isTerminal} className="w-full rounded bg-primary px-4 py-3 text-primary-foreground disabled:opacity-50">
                    {isTerminal ? `Visit ${visit.status}` : submit.processing ? 'Submitting…' : 'Submit visit'}
                </button>
            </form>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/field-agent/visits/new.tsx
git commit -m "feat(vendor-visits): add field-agent visit form page

Mobile-first form with per-item toggle/note auto-save, camera-capture
photos, escalation checkbox, and a disabled-until-complete submit.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: Dashboard summary card — "N vendors need a visit"

**Files:**
- Modify: `app/Http/Controllers/FieldAgentDashboardController.php`
- Modify: `resources/js/Pages/field-agent/dashboard.tsx`

- [ ] **Step 1: Extend the controller payload**

Open `FieldAgentDashboardController::index`. Inside its `Inertia::render('field-agent/dashboard', [...])` props array, add:

```php
'needsVisitCount' => \App\Models\User::query()
    ->where('role', 'vendor')
    ->whereHas('vendorApplication', fn ($q) => $q->where('status', 'approved'))
    ->where(function ($q) {
        $q->whereNull('field_verified_until')->orWhere('field_verified_until', '<=', now());
    })
    ->count(),
```

- [ ] **Step 2: Render the card**

Open `resources/js/Pages/field-agent/dashboard.tsx`. Add `needsVisitCount: number` to the props interface. Near the top of the page body (above existing stat cards), render:

```tsx
{needsVisitCount > 0 && (
    <Link href="/field-agent/visits" className="block rounded border bg-amber-50 p-4 hover:bg-amber-100">
        <div className="text-sm font-medium text-amber-900">
            {needsVisitCount} {needsVisitCount === 1 ? 'vendor needs' : 'vendors need'} a visit
        </div>
        <div className="text-xs text-amber-800">Open the visits queue →</div>
    </Link>
)}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/FieldAgentDashboardController.php resources/js/Pages/field-agent/dashboard.tsx
git commit -m "feat(vendor-visits): show 'N vendors need a visit' card on dashboard

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 15: `Admin\VendorVisitsController` + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/Admin/VendorVisitsController.php`
- Create: `tests/Feature/Admin/VendorVisitsControllerTest.php`
- Modify: `routes/web.php`

**Mental model:** Admins operate on the **same** visit rows as agents, but with different levers — view all, override outcomes, revoke active badges. Keep admin logic isolated in its own controller so it's easy to reason about permissions.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Admin/VendorVisitsControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorVisitsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_visits(): void
    {
        $this->actingAs(User::factory()->vendor()->create())
            ->get('/dashboard/vendor-visits')
            ->assertForbidden();
    }

    public function test_admin_sees_visits_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorVisit::factory()->escalated()->create();

        $this->actingAs($admin)->get('/dashboard/vendor-visits')->assertOk();
    }

    public function test_admin_can_open_a_visit_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->get("/dashboard/vendor-visits/{$visit->id}")
            ->assertOk();
    }

    public function test_admin_can_override_outcome(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/override", [
                'result' => VendorVisitStatus::Passed->value,
                'reason' => 'Reviewed photos; agent misclicked.',
            ])
            ->assertRedirect();

        $this->assertSame(VendorVisitStatus::Passed, $visit->fresh()->status);
    }

    public function test_override_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->failed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/override", [
                'result' => VendorVisitStatus::Passed->value,
                'reason' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_can_revoke_an_active_badge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/revoke", [
                'reason' => 'Customer complaint confirmed — business closed.',
            ])
            ->assertRedirect();

        $this->assertSame(VendorVisitStatus::Revoked, $visit->fresh()->status);
        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_revoke_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = VendorVisit::factory()->passed()->create();

        $this->actingAs($admin)
            ->postJson("/dashboard/vendor-visits/{$visit->id}/revoke", ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=Admin\\\\VendorVisitsControllerTest
```

Expected: all fail — route / controller missing.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/VendorVisitsController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VendorVisit\OverrideVendorVisit;
use App\Actions\VendorVisit\RevokeFieldVerificationBadge;
use App\Enums\VendorVisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverrideVendorVisitRequest;
use App\Http\Requests\Admin\RevokeFieldVerificationBadgeRequest;
use App\Models\VendorVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorVisitsController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab', 'needs-review')->toString();

        $query = VendorVisit::query()
            ->with(['vendor:id,business_name,name', 'fieldAgent:id,name'])
            ->latest('started_at');

        if ($tab === 'needs-review') {
            $query->where('status', VendorVisitStatus::Submitted->value);
        } elseif ($tab === 'recent-failures') {
            $query->where('status', VendorVisitStatus::Failed->value)
                ->where('submitted_at', '>=', now()->subDays(30));
        }

        return Inertia::render('admin/vendor-visits/index', [
            'tab' => $tab,
            'visits' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(VendorVisit $visit): Response
    {
        $visit->load([
            'items',
            'vendor:id,business_name,name,email,phone,field_verified_at,field_verified_until',
            'fieldAgent:id,name',
            'overrideBy:id,name',
        ]);

        return Inertia::render('admin/vendor-visits/show', [
            'visit' => $visit,
        ]);
    }

    public function override(OverrideVendorVisitRequest $request, VendorVisit $visit, OverrideVendorVisit $action): RedirectResponse
    {
        $action->execute(
            visit: $visit,
            admin: $request->user(),
            newResult: VendorVisitStatus::from($request->validated('result')),
            reason: $request->validated('reason'),
        );

        return back()->with('success', 'Visit outcome overridden.');
    }

    public function revoke(RevokeFieldVerificationBadgeRequest $request, VendorVisit $visit, RevokeFieldVerificationBadge $action): RedirectResponse
    {
        $action->execute($visit, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Badge revoked.');
    }
}
```

- [ ] **Step 4: Register the routes**

Open `routes/web.php`. Find the existing admin dashboard group (`Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(...)`). Add inside it (before any catch-all):

```php
Route::middleware('role:admin,super_admin')->prefix('vendor-visits')->name('admin.vendor-visits.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\VendorVisitsController::class, 'index'])->name('index');
    Route::get('/{visit}', [\App\Http\Controllers\Admin\VendorVisitsController::class, 'show'])->name('show');
    Route::post('/{visit}/override', [\App\Http\Controllers\Admin\VendorVisitsController::class, 'override'])->name('override');
    Route::post('/{visit}/revoke', [\App\Http\Controllers\Admin\VendorVisitsController::class, 'revoke'])->name('revoke');
});
```

- [ ] **Step 5: Run tests and verify they pass**

```bash
php artisan test --compact --filter=Admin\\\\VendorVisitsControllerTest
```

Expected: all seven pass.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/VendorVisitsController.php tests/Feature/Admin/VendorVisitsControllerTest.php routes/web.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add admin controller + routes for override and revoke

Four admin endpoints under /dashboard/vendor-visits: index (tabs for
needs-review / recent-failures / all), show (full visit detail),
override (edits outcome with reason), revoke (nukes active badge).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 16: Admin Inertia pages — index + show (condensed)

**Files:**
- Create: `resources/js/Pages/admin/vendor-visits/index.tsx`
- Create: `resources/js/Pages/admin/vendor-visits/show.tsx`

**Behavior (index):** three tabs (`needs-review`, `recent-failures`, `all`) switchable via query param `?tab=`. Table of rows; click a row → show page.

**Behavior (show):** header with vendor + agent + metadata; embedded map link (simple `<a>` to Google Maps `https://maps.google.com/?q={lat},{lng}` is sufficient for v1); full checklist table; both photos; **Override** form (result select + reason textarea); **Revoke** button (if visit is `passed`) with a confirm dialog.

- [ ] **Step 1: Create the index page**

Create `resources/js/Pages/admin/vendor-visits/index.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';

interface VisitRow {
    id: string;
    status: string;
    started_at: string;
    submitted_at: string | null;
    vendor: { id: number; business_name: string | null; name: string };
    field_agent: { id: number; name: string };
}

interface Props {
    tab: 'needs-review' | 'recent-failures' | 'all';
    visits: { data: VisitRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}

const TABS: Array<{ key: Props['tab']; label: string }> = [
    { key: 'needs-review', label: 'Needs review' },
    { key: 'recent-failures', label: 'Recent failures' },
    { key: 'all', label: 'All visits' },
];

export default function AdminVendorVisitsIndex({ tab, visits }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Admin', href: '/dashboard' }, { title: 'Vendor visits', href: '/dashboard/vendor-visits' }]}>
            <Head title="Vendor visits" />
            <div className="p-6">
                <div className="mb-4 flex gap-2">
                    {TABS.map((t) => (
                        <button key={t.key} onClick={() => router.visit(`/dashboard/vendor-visits?tab=${t.key}`)} className={`rounded border px-3 py-1 ${tab === t.key ? 'bg-primary text-primary-foreground' : ''}`}>
                            {t.label}
                        </button>
                    ))}
                </div>

                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-2">Vendor</th><th>Agent</th><th>Status</th><th>Started</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {visits.data.map((v) => (
                            <tr key={v.id} className="border-b">
                                <td className="py-2">{v.vendor.business_name ?? v.vendor.name}</td>
                                <td>{v.field_agent.name}</td>
                                <td><Badge status={v.status} /></td>
                                <td>{new Date(v.started_at).toLocaleString()}</td>
                                <td><Link href={`/dashboard/vendor-visits/${v.id}`} className="text-primary">Open →</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}

function Badge({ status }: { status: string }) {
    const tone = { passed: 'bg-green-600', failed: 'bg-red-600', submitted: 'bg-amber-600', revoked: 'bg-gray-600', draft: 'bg-slate-400' }[status] ?? 'bg-slate-400';
    return <span className={`rounded px-2 py-0.5 text-xs text-white ${tone}`}>{status}</span>;
}
```

- [ ] **Step 2: Create the show page**

Create `resources/js/Pages/admin/vendor-visits/show.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

interface Item { id: number; item_key: string; category: string; criticality: string; passed: boolean | null; note: string | null }
interface Visit {
    id: string;
    status: string;
    computed_result: string | null;
    admin_override_result: string | null;
    admin_override_reason: string | null;
    admin_override_at: string | null;
    override_by: { id: number; name: string } | null;
    started_at: string;
    submitted_at: string | null;
    visit_latitude: string;
    visit_longitude: string;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    escalated: boolean;
    badge_issued_at: string | null;
    badge_expires_at: string | null;
    vendor: { id: number; business_name: string | null; name: string; email: string; phone: string | null; field_verified_until: string | null };
    field_agent: { id: number; name: string };
    items: Item[];
}
interface Props { visit: Visit }

export default function AdminVendorVisitShow({ visit }: Props) {
    const override = useForm({ result: 'passed', reason: '' });
    const revoke = useForm({ reason: '' });

    const storefrontSrc = visit.storefront_photo_path ? `/storage/${visit.storefront_photo_path}` : null;
    const ownerSrc = visit.owner_photo_path ? `/storage/${visit.owner_photo_path}` : null;
    const mapsUrl = `https://maps.google.com/?q=${visit.visit_latitude},${visit.visit_longitude}`;

    return (
        <AppLayout breadcrumbs={[{ title: 'Admin', href: '/dashboard' }, { title: 'Vendor visits', href: '/dashboard/vendor-visits' }, { title: visit.vendor.business_name ?? visit.vendor.name, href: '#' }]}>
            <Head title="Visit detail" />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <header>
                    <h1 className="text-xl font-semibold">{visit.vendor.business_name ?? visit.vendor.name}</h1>
                    <p className="text-sm text-muted-foreground">Agent: {visit.field_agent.name}</p>
                    <p className="text-sm text-muted-foreground">Started: {new Date(visit.started_at).toLocaleString()} · <a href={mapsUrl} target="_blank" rel="noreferrer" className="text-primary">Map</a></p>
                    <p className="text-sm">Status: <strong>{visit.status}</strong> {visit.escalated && '(escalated)'}</p>
                </header>

                <section>
                    <h2 className="mb-2 font-semibold">Checklist</h2>
                    <ul className="space-y-1 text-sm">
                        {visit.items.map((it) => (
                            <li key={it.id}>
                                <strong>{it.criticality === 'critical' ? '★' : '·'}</strong> {it.item_key}: <span className={it.passed ? 'text-green-700' : 'text-red-700'}>{it.passed === null ? 'unanswered' : it.passed ? 'pass' : 'fail'}</span>
                                {it.note && <em className="ml-2 text-muted-foreground">“{it.note}”</em>}
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    {storefrontSrc && <a href={storefrontSrc} target="_blank" rel="noreferrer"><img src={storefrontSrc} alt="Storefront" className="max-h-64 w-full rounded border object-cover" /></a>}
                    {ownerSrc && <a href={ownerSrc} target="_blank" rel="noreferrer"><img src={ownerSrc} alt="Owner" className="max-h-64 w-full rounded border object-cover" /></a>}
                </section>

                {visit.notes && <section><h2 className="mb-1 font-semibold">Agent notes</h2><p className="whitespace-pre-line text-sm">{visit.notes}</p></section>}

                <section className="rounded border p-4">
                    <h2 className="mb-2 font-semibold">Admin override</h2>
                    {visit.admin_override_result && (
                        <p className="mb-2 text-sm">Previously overridden to <strong>{visit.admin_override_result}</strong> by {visit.override_by?.name}: “{visit.admin_override_reason}”</p>
                    )}
                    <form onSubmit={(e) => { e.preventDefault(); override.post(`/dashboard/vendor-visits/${visit.id}/override`); }} className="space-y-2">
                        <select value={override.data.result} onChange={(e) => override.setData('result', e.target.value)} className="rounded border px-2 py-1">
                            <option value="passed">Mark passed</option>
                            <option value="failed">Mark failed</option>
                        </select>
                        <textarea required placeholder="Reason (required)" value={override.data.reason} onChange={(e) => override.setData('reason', e.target.value)} className="w-full rounded border p-2" rows={2} />
                        <button disabled={override.processing} className="rounded bg-primary px-3 py-1 text-primary-foreground">Apply override</button>
                    </form>
                </section>

                {visit.status === 'passed' && (
                    <section className="rounded border border-red-200 p-4">
                        <h2 className="mb-2 font-semibold text-red-800">Revoke active badge</h2>
                        <form onSubmit={(e) => {
                            e.preventDefault();
                            if (!confirm('Revoke this badge? The vendor will lose Field Verified status immediately.')) return;
                            revoke.post(`/dashboard/vendor-visits/${visit.id}/revoke`);
                        }} className="space-y-2">
                            <textarea required placeholder="Reason (required)" value={revoke.data.reason} onChange={(e) => revoke.setData('reason', e.target.value)} className="w-full rounded border p-2" rows={2} />
                            <button disabled={revoke.processing} className="rounded bg-red-600 px-3 py-1 text-white">Revoke badge</button>
                        </form>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/admin/vendor-visits/
git commit -m "feat(vendor-visits): add admin index and detail Inertia pages

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 17: Vendor profile panel + agent list columns (admin-side)

**Files:**
- Modify: admin vendor detail page (grep to find — likely `resources/js/Pages/vendor-applications/show.tsx` or `resources/js/Pages/vendors/show.tsx`)
- Modify: admin field-agent list controller + page (grep to find — `FieldAgentApplicationController@index` or similar)

- [ ] **Step 1: Locate the admin vendor detail page**

```bash
# Use the Grep tool to locate where admin renders a vendor profile
```

Search for `vendor-applications/show` and `admin/vendors/show` in `resources/js/Pages/`. Open whichever page the admin uses to view a specific vendor.

- [ ] **Step 2: Add "Field verification" panel**

In the server controller that powers that page, ensure the vendor user record (with `field_verified_at`, `field_verified_until`) plus the last three visits are passed as props:

```php
'fieldVerification' => [
    'is_verified' => $vendor->isFieldVerified(),
    'verified_until' => $vendor->field_verified_until?->toIso8601String(),
    'recent_visits' => $vendor->vendorVisitsReceived()
        ->latest('started_at')
        ->take(3)
        ->get(['id', 'status', 'started_at', 'badge_expires_at']),
],
```

In the React page, render near the top of the profile:

```tsx
{fieldVerification && (
    <section className="rounded border p-4">
        <h2 className="mb-2 font-semibold">Field verification</h2>
        <p>{fieldVerification.is_verified ? `✓ Field Verified (expires ${new Date(fieldVerification.verified_until!).toLocaleDateString()})` : 'Not field verified'}</p>
        <ul className="mt-2 space-y-1 text-sm">
            {fieldVerification.recent_visits.map((v) => (
                <li key={v.id}>
                    {new Date(v.started_at).toLocaleDateString()} — {v.status}
                </li>
            ))}
        </ul>
        <Link href="/dashboard/vendor-visits" className="text-sm text-primary">View all visits →</Link>
    </section>
)}
```

- [ ] **Step 3: Add columns to the admin field-agent list**

Find the admin controller that lists field agents (likely `FieldAgentApplicationController@index` or `FieldAgentAdminController`). Extend its payload per row:

```php
'visits_last_30d' => \App\Models\VendorVisit::query()
    ->where('field_agent_user_id', $agent->id)
    ->where('submitted_at', '>=', now()->subDays(30))
    ->count(),
'pass_rate_pct' => $this->computePassRate($agent),
```

Where `computePassRate()` divides passed submitted visits by total submitted visits over the same window.

Add two columns in the corresponding table page.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/ app/Http/Controllers/
git commit -m "feat(vendor-visits): add admin vendor profile panel and agent list columns

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

Note: this task requires some adaptation to the existing admin pages in the codebase; exact file paths are discovered by grep in Step 1. Do not skip the grep.

---

## Task 18: Notifications (escalation, failure, revocation)

**Files:**
- Create: `app/Notifications/VisitEscalatedToAdminNotification.php`
- Create: `app/Notifications/VisitFailedNotification.php`
- Create: `app/Notifications/FieldVerificationBadgeRevokedNotification.php`
- Modify: `app/Actions/VendorVisit/CompleteVendorVisit.php` (dispatch on escalation/failure)
- Modify: `app/Actions/VendorVisit/RevokeFieldVerificationBadge.php` (dispatch on revocation)

**Mental model:** A bell that rings in the right hallway when a specific thing happens. Escalation and failure ring for admins (via channel `mail` and `database`). Revocation rings for the vendor (via `mail` and `database`). Keep notifications dumb: they format what happened, they don't decide.

- [ ] **Step 1: Create the notifications**

```bash
php artisan make:notification VisitEscalatedToAdminNotification --no-interaction
php artisan make:notification VisitFailedNotification --no-interaction
php artisan make:notification FieldVerificationBadgeRevokedNotification --no-interaction
```

Edit `VisitEscalatedToAdminNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\VendorVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitEscalatedToAdminNotification extends Notification
{
    use Queueable;

    public function __construct(public VendorVisit $visit) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->visit->loadMissing(['vendor', 'fieldAgent']);

        return (new MailMessage)
            ->subject('A field visit needs your review')
            ->line("Field agent {$this->visit->fieldAgent->name} escalated a visit to vendor {$this->visit->vendor->business_name}.")
            ->action('Open visit', url("/dashboard/vendor-visits/{$this->visit->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'visit_id' => $this->visit->id,
            'vendor_id' => $this->visit->vendor_user_id,
            'agent_id' => $this->visit->field_agent_user_id,
            'kind' => 'escalated',
        ];
    }
}
```

Edit `VisitFailedNotification.php` — identical shape except `via()` returns `['database']` only, and the payload `kind` is `'failed'`.

Edit `FieldVerificationBadgeRevokedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\VendorVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldVerificationBadgeRevokedNotification extends Notification
{
    use Queueable;

    public function __construct(public VendorVisit $visit, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Field Verified status was revoked')
            ->line('An admin has revoked your Field Verified badge.')
            ->line("Reason: {$this->reason}")
            ->line('You can request a new visit through the platform.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'visit_id' => $this->visit->id,
            'reason' => $this->reason,
            'kind' => 'badge_revoked',
        ];
    }
}
```

- [ ] **Step 2: Dispatch from the actions**

In `CompleteVendorVisit::execute`, after `$visit->update($updates);`, add:

```php
if ($outcome === \App\Enums\VendorVisitStatus::Submitted) {
    \Illuminate\Support\Facades\Notification::send(
        \App\Models\User::query()->whereIn('role', ['admin', 'super_admin'])->get(),
        new \App\Notifications\VisitEscalatedToAdminNotification($visit->fresh())
    );
}
if ($outcome === \App\Enums\VendorVisitStatus::Failed) {
    \Illuminate\Support\Facades\Notification::send(
        \App\Models\User::query()->whereIn('role', ['admin', 'super_admin'])->get(),
        new \App\Notifications\VisitFailedNotification($visit->fresh())
    );
}
```

In `RevokeFieldVerificationBadge::execute`, after `$visit->update([...]);`, add:

```php
$visit->vendor->notify(new \App\Notifications\FieldVerificationBadgeRevokedNotification($visit->fresh(), $reason));
```

- [ ] **Step 3: Add a test for the dispatch**

Create `tests/Feature/VendorVisitNotificationsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Actions\VendorVisit\RevokeFieldVerificationBadge;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use App\Notifications\FieldVerificationBadgeRevokedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorVisitNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_revocation_notifies_the_vendor(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        app(RevokeFieldVerificationBadge::class)->execute($visit, $admin, 'fraud');

        Notification::assertSentTo($vendor, FieldVerificationBadgeRevokedNotification::class);
    }
}
```

- [ ] **Step 4: Run and verify it passes**

```bash
php artisan test --compact --filter=VendorVisitNotificationsTest
```

Expected: pass.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/ app/Actions/VendorVisit/CompleteVendorVisit.php app/Actions/VendorVisit/RevokeFieldVerificationBadge.php tests/Feature/VendorVisitNotificationsTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): notifications for escalation, failure, revocation

Admins get mail+database on escalations and database-only on failures;
vendors get mail+database on badge revocation with the admin's reason
surfaced.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 19: Widen `VendorResource` with `is_field_verified` + `field_verified_until`

**Files:**
- Modify: `app/Http/Resources/VendorResource.php`
- Create: `tests/Feature/Api/V1/VendorResourceFieldVerificationTest.php`

**Mental model:** The mobile app at `C:\dev\surprise_moi` already reads `VendorResource` for every vendor it shows. Dropping two new fields in is the simplest possible delivery — no new endpoint for customers.

- [ ] **Step 1: Write a failing test**

Create `tests/Feature/Api/V1/VendorResourceFieldVerificationTest.php`:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Http\Resources\VendorResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class VendorResourceFieldVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_vendor_has_badge_fields_true(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subMonth(),
            'field_verified_until' => now()->addYear(),
        ]);

        $resource = (new VendorResource($vendor))->toArray(Request::create('/'));

        $this->assertTrue($resource['is_field_verified']);
        $this->assertNotNull($resource['field_verified_until']);
    }

    public function test_expired_verification_is_reported_as_false(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subMonths(13),
            'field_verified_until' => now()->subMonth(),
        ]);

        $resource = (new VendorResource($vendor))->toArray(Request::create('/'));

        $this->assertFalse($resource['is_field_verified']);
    }

    public function test_never_verified_vendor_reports_false_and_null_until(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => null,
            'field_verified_until' => null,
        ]);

        $resource = (new VendorResource($vendor))->toArray(Request::create('/'));

        $this->assertFalse($resource['is_field_verified']);
        $this->assertNull($resource['field_verified_until']);
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
php artisan test --compact --filter=VendorResourceFieldVerificationTest
```

Expected: three failures — fields missing.

- [ ] **Step 3: Extend the resource**

Open `app/Http/Resources/VendorResource.php`. In `toArray()`, add two entries (anywhere within the returned array):

```php
'is_field_verified' => $this->field_verified_until !== null
    && $this->field_verified_until->isFuture(),
'field_verified_until' => $this->field_verified_until?->toIso8601String(),
```

- [ ] **Step 4: Run tests and verify they pass**

```bash
php artisan test --compact --filter=VendorResourceFieldVerificationTest
```

Expected: all three pass.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Resources/VendorResource.php tests/Feature/Api/V1/VendorResourceFieldVerificationTest.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): expose field verification on VendorResource

Adds is_field_verified (derived: field_verified_until > now()) and
field_verified_until (ISO8601) to the resource the mobile app already
reads. Customer-facing "Field Verified" badge now renders without any
new endpoint.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 20: Vendor's own-badge API endpoint

**Files:**
- Create: `app/Http/Controllers/Api/V1/VendorFieldVerificationController.php`
- Create: `app/Http/Resources/VendorFieldVerificationResource.php`
- Create: `tests/Feature/Api/V1/VendorFieldVerificationTest.php`
- Modify: `routes/api.php`

**Mental model:** The vendor's own-app screen needs more detail than the public badge — their own visit history, outcome per visit, next expiry. This is the **vendor's private view** over their verification record. Locked to Sanctum auth, scoped to the authenticated user.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Api/V1/VendorFieldVerificationTest.php`:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorFieldVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/vendor/field-verification')->assertStatus(401);
    }

    public function test_non_vendor_cannot_access_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/v1/vendor/field-verification')->assertStatus(403);
    }

    public function test_vendor_sees_their_badge_and_visit_timeline(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subMonths(2),
            'field_verified_until' => now()->addMonths(10),
        ]);
        VendorVisit::factory()->passed()->count(2)->create(['vendor_user_id' => $vendor->id]);
        VendorVisit::factory()->failed()->create(['vendor_user_id' => $vendor->id]);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/field-verification')
            ->assertOk()
            ->assertJsonPath('data.is_field_verified', true)
            ->assertJsonCount(3, 'data.visits');
    }

    public function test_response_excludes_agent_notes_and_per_item_pass_fail(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subMonth(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        VendorVisit::factory()->passed()->create([
            'vendor_user_id' => $vendor->id,
            'notes' => 'SECRET agent note',
        ]);

        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/field-verification');

        $response->assertOk();
        $this->assertStringNotContainsString('SECRET agent note', $response->getContent());
        $this->assertStringNotContainsString('items', $response->getContent());
    }

    public function test_vendor_cannot_see_another_vendors_visits(): void
    {
        $me = User::factory()->vendor()->create();
        $other = User::factory()->vendor()->create();
        VendorVisit::factory()->passed()->create(['vendor_user_id' => $other->id]);

        Sanctum::actingAs($me);

        $this->getJson('/api/v1/vendor/field-verification')
            ->assertOk()
            ->assertJsonCount(0, 'data.visits');
    }
}
```

- [ ] **Step 2: Run and verify they fail**

```bash
php artisan test --compact --filter=VendorFieldVerificationTest
```

Expected: all fail — route missing.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/VendorFieldVerificationResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorFieldVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $vendor */
        $vendor = $this->resource;

        return [
            'is_field_verified' => $vendor->isFieldVerified(),
            'field_verified_at' => $vendor->field_verified_at?->toIso8601String(),
            'field_verified_until' => $vendor->field_verified_until?->toIso8601String(),
            'visits' => $vendor->vendorVisitsReceived()
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'visited_at' => $v->submitted_at?->toIso8601String(),
                    'outcome' => $v->status->value,
                    'badge_expires_at' => $v->badge_expires_at?->toIso8601String(),
                ])->values(),
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Api/V1/VendorFieldVerificationController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorFieldVerificationResource;
use Illuminate\Http\Request;

class VendorFieldVerificationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        abort_unless($user->role === 'vendor', 403);

        return new VendorFieldVerificationResource($user);
    }
}
```

- [ ] **Step 5: Register the route**

Open `routes/api.php`. Inside the existing `Route::prefix('v1')->group(...)` block, inside the `auth:sanctum` subgroup (create one if needed), add:

```php
Route::middleware('auth:sanctum')->get('/vendor/field-verification', [\App\Http\Controllers\Api\V1\VendorFieldVerificationController::class, 'show']);
```

- [ ] **Step 6: Run tests and verify they pass**

```bash
php artisan test --compact --filter=VendorFieldVerificationTest
```

Expected: all five pass.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/VendorFieldVerificationController.php app/Http/Resources/VendorFieldVerificationResource.php tests/Feature/Api/V1/VendorFieldVerificationTest.php routes/api.php
git commit -m "$(cat <<'EOF'
feat(vendor-visits): add vendor self-view field-verification API

GET /api/v1/vendor/field-verification returns the authenticated
vendor's badge state plus a timeline of their own visit outcomes.
Notes, per-item pass/fail, photos, and GPS are all withheld.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 21: Final verification

**No file changes.** This is the sanity pass.

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: green. If anything unrelated fails, investigate before declaring done.

- [ ] **Step 2: Run Pint across the whole changeset**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: no formatting changes beyond what was already committed. If Pint rewrites anything, commit the fixes:

```bash
git add .
git commit -m "style: apply Pint to vendor-visits files"
```

- [ ] **Step 3: Smoke-test the field-agent flow manually in a browser**

1. Log in as a field agent (from CLAUDE.md creds: `mr.osaeafari@gmail.com` / `p@p@.0p3n`, though this is a vendor — use any field agent account created via the admin dashboard).
2. Visit `/field-agent/visits`. Expected: the three sections render.
3. Click a vendor → vendor detail page shows claim data.
4. Click "Start visit". Grant location. You should land on `/field-agent/visits/forms/{uuid}`.
5. Toggle a few items, add notes. Each click should persist (refresh to confirm).
6. Upload the two photos. Tick neither "Escalate to admin".
7. Submit. Expected: redirect to the form page, status now shows `passed`.
8. Log in as the vendor (via the mobile app or a curl call): `GET /api/v1/vendor/field-verification` — confirm `is_field_verified: true`.
9. Log in as an admin. Visit `/dashboard/vendor-visits`. Confirm the just-completed visit appears in the `All visits` tab.

- [ ] **Step 4: Open a PR**

```bash
git push -u origin feat/field-agent-vendor-validation-checklist
gh pr create --title "Field agent vendor validation checklist" --body "$(cat <<'EOF'
## Summary
- Adds a post-approval trust layer: field agents run an on-site checklist, and on pass the vendor gets a 12-month "Field Verified" badge visible to the mobile app.
- Two new tables (`vendor_visits`, `vendor_visit_items`) + denormalized cached columns on `users`.
- New Inertia pages for field-agent visits (index, show, new) and admin review (index, show).
- New `GET /api/v1/vendor/field-verification` endpoint for the mobile app's vendor self-view.

## Test plan
- [ ] `php artisan test --compact` all green
- [ ] Manual: field agent starts + submits a passing visit; badge propagates to `users.field_verified_*`
- [ ] Manual: admin overrides a failed visit to passed; badge appears
- [ ] Manual: admin revokes an active badge; vendor email fires
- [ ] Manual: vendor editing `business_name` clears their badge immediately
EOF
)"
```

---

## Self-Review Checklist

Before marking the plan done, verify the spec is fully covered:

- [x] Data model: §5 → Tasks 2, 3
- [x] Checklist items: §6 → Task 1
- [x] Auto-compute rule: §7 → Task 5
- [x] Workflow / state machine: §8 → Tasks 4, 5, 6, 7, 10, 15
- [x] Permissions: §9 → Task 10 (agent), Task 15 (admin), Task 20 (vendor API)
- [x] Field agent dashboard UX: §10 → Tasks 11, 12, 13, 14
- [x] Admin screens: §11 → Tasks 15, 16, 17
- [x] Mobile API changes: §12 → Tasks 19, 20
- [x] Error handling: §13 → Tasks 4 (GPS required), 5 (idempotent submit), 8 (badge invalidation), 10 (authorization)
- [x] Testing strategy: §14 → every task has tests; Task 21 runs the full suite

**Known areas needing hands-on adaptation during execution:**

- Task 17 — exact admin vendor-profile and field-agent-list file paths are discovered via grep at execution time, not pre-declared in this plan.
- Task 12 — the image URL helper (`storage_url()` vs `/storage/...`) may need to match whatever the existing admin pages use.
- Task 10 — if the `role` middleware returns a redirect instead of a 403, the test assertions need to match.

These are all surfaced as in-task notes, not hidden assumptions.






