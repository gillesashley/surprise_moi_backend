# Bulk Referral Code Generation & Dynamic Registration Bonus — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable admins to bulk-generate prefixed referral codes per user category and make registration bonuses dynamically calculated from per-category percentages × tier onboarding fees.

**Architecture:** Two independent features sharing a common code prefix system. Feature 1 adds a `bulkGenerateCodes()` service method and a modal-driven admin UI. Feature 2 adds 5 new settings rows, modifies `activateReferral()` to compute bonuses dynamically, and extends the vendor onboarding settings page. Both features modify `ReferralCode::generateUniqueCode()` to support role-based prefixes.

**Tech Stack:** Laravel 12, PHP 8.2, React 19, Inertia v2, MUI, PHPUnit

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `app/Models/ReferralCode.php` | Modify | Add `ROLE_PREFIXES` constant, update `generateUniqueCode()` for prefix support |
| `app/Services/ReferralService.php` | Modify | Add `bulkGenerateCodes()`, `calculateRegistrationBonus()`, update `activateReferral()` and `createReferralCode()` |
| `database/migrations/XXXX_add_referral_bonus_percentage_settings.php` | Create | Seed 5 new percentage settings |
| `app/Http/Controllers/ReferralCodeController.php` | Modify | Add `bulkGeneratePreview()` and `bulkGenerate()` actions |
| `app/Http/Requests/BulkGenerateReferralCodeRequest.php` | Create | Validate role for bulk generation |
| `app/Http/Controllers/Settings/VendorOnboardingController.php` | Modify | Include 5 new percentage settings in update validation |
| `app/Http/Controllers/Api/V1/ReferralCodeController.php` | Modify | Pass user role for prefix on code creation |
| `app/Http/Controllers/Api/V1/MyReferralController.php` | Modify | Pass user role for prefix on auto-creation, include bonus info in response |
| `routes/web.php` | Modify | Add bulk generate routes |
| `resources/js/pages/settings/vendor-onboarding.tsx` | Modify | Add Referral Bonus Percentages section |
| `resources/js/pages/referral-codes/index.tsx` | Modify | Add Bulk Generate button and modal |
| `resources/js/pages/referral-codes/create.tsx` | Modify | Replace manual bonus input with computed preview |
| `tests/Feature/ReferralCodePrefixTest.php` | Create | Test prefix generation per role |
| `tests/Feature/BulkGenerateReferralCodeTest.php` | Create | Test bulk generation flow |
| `tests/Feature/DynamicRegistrationBonusTest.php` | Create | Test dynamic bonus calculation |
| `tests/Feature/ReferralBonusSettingsTest.php` | Create | Test settings CRUD for percentages |

---

## Task 1: Add Role Prefix System to ReferralCode Model

**Files:**
- Modify: `app/Models/ReferralCode.php`
- Create: `tests/Feature/ReferralCodePrefixTest.php`

- [ ] **Step 1: Write the failing test**

Create the test file:

```bash
cd C:/dev/surprise_moi_backend && php artisan make:test ReferralCodePrefixTest --phpunit --no-interaction
```

Replace contents of `tests/Feature/ReferralCodePrefixTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCodePrefixTest extends TestCase
{
    use RefreshDatabase;

    public function testGetPrefixForRoleReturnsCorrectPrefix(): void
    {
        $this->assertEquals('CU-', ReferralCode::getPrefixForRole('customer'));
        $this->assertEquals('VD-', ReferralCode::getPrefixForRole('vendor'));
        $this->assertEquals('IN-', ReferralCode::getPrefixForRole('influencer'));
        $this->assertEquals('FA-', ReferralCode::getPrefixForRole('field_agent'));
        $this->assertEquals('MK-', ReferralCode::getPrefixForRole('marketer'));
    }

    public function testGetPrefixForRoleReturnsEmptyStringForUnmappedRole(): void
    {
        $this->assertEquals('', ReferralCode::getPrefixForRole('admin'));
        $this->assertEquals('', ReferralCode::getPrefixForRole('super_admin'));
    }

    public function testGenerateUniqueCodeWithPrefixIncludesPrefix(): void
    {
        $code = ReferralCode::generateUniqueCode('VD-');
        $this->assertStringStartsWith('VD-', $code);
        $this->assertEquals(11, strlen($code)); // 3 prefix + 8 random
    }

    public function testGenerateUniqueCodeWithoutPrefixRemainsEightChars(): void
    {
        $code = ReferralCode::generateUniqueCode();
        $this->assertEquals(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
    }

    public function testBootCreatingEventUsesPrefixWhenSet(): void
    {
        $user = User::factory()->vendor()->create();
        $code = ReferralCode::create([
            'influencer_id' => $user->id,
            'prefix' => 'VD-',
            'is_active' => true,
        ]);

        $this->assertStringStartsWith('VD-', $code->code);
    }

    public function testBootCreatingEventGeneratesUnprefixedCodeByDefault(): void
    {
        $user = User::factory()->influencer()->create();
        $code = ReferralCode::create([
            'influencer_id' => $user->id,
            'is_active' => true,
        ]);

        $this->assertEquals(8, strlen($code->code));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralCodePrefixTest
```

Expected: FAIL — `getPrefixForRole` method does not exist.

- [ ] **Step 3: Implement prefix system in ReferralCode model**

In `app/Models/ReferralCode.php`, add the constant after `use HasFactory, SoftDeletes;`:

```php
    /** @var array<string, string> Role-to-prefix mapping for referral codes. */
    public const ROLE_PREFIXES = [
        'customer' => 'CU-',
        'vendor' => 'VD-',
        'influencer' => 'IN-',
        'field_agent' => 'FA-',
        'marketer' => 'MK-',
    ];
```

Update the `$fillable` array to include `'prefix'`:

```php
    protected $fillable = [
        'influencer_id',
        'code',
        'prefix',
        'description',
        'is_active',
        'usage_count',
        'max_usages',
        'registration_bonus',
        'commission_rate',
        'commission_duration_months',
        'expires_at',
    ];
```

Note: `prefix` is NOT a database column — it's a transient attribute used only during the `creating` event to influence code generation. It gets automatically excluded by Eloquent when saving because the column doesn't exist in the DB schema. Add it to the model's `$guarded` exclusion instead — actually, simpler: keep it in fillable and strip it before save. Better approach: don't put it in fillable at all. Use a public property.

Remove `'prefix'` from fillable. Instead add a public property:

```php
    /**
     * Transient prefix used during code generation (not persisted).
     */
    public ?string $prefix = null;
```

Update the `boot()` method:

```php
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ReferralCode $code) {
            if (empty($code->code)) {
                $code->code = static::generateUniqueCode($code->prefix ?? '');
            }
        });
    }
```

Update `generateUniqueCode()`:

```php
    /**
     * Generate a unique referral code, optionally with a role prefix.
     */
    public static function generateUniqueCode(string $prefix = ''): string
    {
        do {
            $code = $prefix . strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }
```

Add the static helper:

```php
    /**
     * Get the code prefix for a given user role.
     */
    public static function getPrefixForRole(string $role): string
    {
        return static::ROLE_PREFIXES[$role] ?? '';
    }
```

- [ ] **Step 4: Fix test to use the public property approach**

Update the test `testBootCreatingEventUsesPrefixWhenSet` — since prefix is a public property, not fillable, set it directly:

```php
    public function testBootCreatingEventUsesPrefixWhenSet(): void
    {
        $user = User::factory()->vendor()->create();
        $code = new ReferralCode([
            'influencer_id' => $user->id,
            'is_active' => true,
        ]);
        $code->prefix = 'VD-';
        $code->save();

        $this->assertStringStartsWith('VD-', $code->code);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralCodePrefixTest
```

Expected: All 6 tests PASS.

- [ ] **Step 6: Run Pint**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add app/Models/ReferralCode.php tests/Feature/ReferralCodePrefixTest.php && git commit -m "feat(referral): add role-based prefix system to ReferralCode model"
```

---

## Task 2: Migration — Add Referral Bonus Percentage Settings

**Files:**
- Create: `database/migrations/XXXX_add_referral_bonus_percentage_settings.php`

- [ ] **Step 1: Create the migration**

```bash
cd C:/dev/surprise_moi_backend && php artisan make:migration add_referral_bonus_percentage_settings --no-interaction
```

- [ ] **Step 2: Write the migration code**

Replace the generated migration contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'referral_bonus_customer_pct',
                'value' => '15.00',
                'type' => 'number',
                'description' => 'Customer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_vendor_pct',
                'value' => '20.00',
                'type' => 'number',
                'description' => 'Vendor referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_influencer_pct',
                'value' => '25.00',
                'type' => 'number',
                'description' => 'Influencer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_field_agent_pct',
                'value' => '30.00',
                'type' => 'number',
                'description' => 'Field Agent referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_marketer_pct',
                'value' => '20.00',
                'type' => 'number',
                'description' => 'Marketer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'referral_bonus_customer_pct',
            'referral_bonus_vendor_pct',
            'referral_bonus_influencer_pct',
            'referral_bonus_field_agent_pct',
            'referral_bonus_marketer_pct',
        ])->delete();
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
cd C:/dev/surprise_moi_backend && php artisan migrate --no-interaction
```

Expected: Migration runs successfully.

- [ ] **Step 4: Verify settings exist**

```bash
cd C:/dev/surprise_moi_backend && php artisan tinker --execute="echo App\Models\Setting::whereIn('key', ['referral_bonus_customer_pct', 'referral_bonus_vendor_pct', 'referral_bonus_influencer_pct', 'referral_bonus_field_agent_pct', 'referral_bonus_marketer_pct'])->count();"
```

Expected: `5`

- [ ] **Step 5: Run Pint and commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add database/migrations/*add_referral_bonus_percentage_settings* && git commit -m "feat(referral): add referral bonus percentage settings migration"
```

---

## Task 3: Dynamic Bonus Calculation in ReferralService

**Files:**
- Modify: `app/Services/ReferralService.php`
- Create: `tests/Feature/DynamicRegistrationBonusTest.php`

- [ ] **Step 1: Write the failing test**

```bash
cd C:/dev/surprise_moi_backend && php artisan make:test DynamicRegistrationBonusTest --phpunit --no-interaction
```

Replace contents of `tests/Feature/DynamicRegistrationBonusTest.php`:

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
use Tests\TestCase;

class DynamicRegistrationBonusTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    public function testCalculateRegistrationBonusForInfluencerTier1(): void
    {
        Setting::set('referral_bonus_influencer_pct', 25.00, 'number');
        Setting::set('vendor_tier1_onboarding_fee', 150.00, 'number');

        $bonus = $this->service->calculateRegistrationBonus('influencer', 1);

        $this->assertEquals(37.50, $bonus);
    }

    public function testCalculateRegistrationBonusForVendorTier2(): void
    {
        Setting::set('referral_bonus_vendor_pct', 20.00, 'number');
        Setting::set('vendor_tier2_onboarding_fee', 100.00, 'number');

        $bonus = $this->service->calculateRegistrationBonus('vendor', 2);

        $this->assertEquals(20.00, $bonus);
    }

    public function testCalculateRegistrationBonusReturnsZeroForMissingPercentage(): void
    {
        // No percentage setting exists for this role
        $bonus = $this->service->calculateRegistrationBonus('admin', 1);

        $this->assertEquals(0.0, $bonus);
    }

    public function testActivateReferralUseDynamicBonusForEarningCapableRole(): void
    {
        Setting::set('referral_bonus_influencer_pct', 25.00, 'number');
        Setting::set('vendor_tier1_onboarding_fee', 150.00, 'number');

        $influencer = User::factory()->influencer()->create();
        $vendor = User::factory()->vendor()->create();

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'registration_bonus' => 0, // new-style: no stored bonus
        ]);

        $vendorApp = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
            'has_business_certificate' => true, // Tier 1
        ]);

        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $vendorApp->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $activatedReferral = $this->service->activateReferral($vendorApp);

        $this->assertNotNull($activatedReferral);
        $this->assertEquals(Referral::STATUS_ACTIVE, $activatedReferral->status);

        $earning = Earning::where('user_id', $influencer->id)
            ->where('earning_type', Earning::TYPE_REFERRAL_BONUS)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(37.50, (float) $earning->amount);
    }

    public function testActivateReferralFallsBackToStoredBonusForLegacyCodes(): void
    {
        $influencer = User::factory()->influencer()->create();
        $vendor = User::factory()->vendor()->create();

        $referralCode = ReferralCode::factory()->create([
            'influencer_id' => $influencer->id,
            'registration_bonus' => 50.00, // legacy: stored bonus
        ]);

        $vendorApp = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'referral_code_id' => $referralCode->id,
            'has_business_certificate' => true,
        ]);

        $referral = Referral::factory()->create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $influencer->id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $vendorApp->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        $this->service->activateReferral($vendorApp);

        $earning = Earning::where('user_id', $influencer->id)
            ->where('earning_type', Earning::TYPE_REFERRAL_BONUS)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(50.00, (float) $earning->amount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=DynamicRegistrationBonusTest
```

Expected: FAIL — `calculateRegistrationBonus` method does not exist.

- [ ] **Step 3: Add calculateRegistrationBonus method to ReferralService**

In `app/Services/ReferralService.php`, add this method after `createReferralCode()` (around line 70):

```php
    /**
     * Calculate the registration bonus for a referrer based on their role
     * and the referred person's vendor tier.
     *
     * Returns: (role percentage / 100) × tier onboarding fee.
     * Returns 0 if no percentage setting exists for the role.
     */
    public function calculateRegistrationBonus(string $referrerRole, int $vendorTier): float
    {
        $percentage = (float) Setting::get("referral_bonus_{$referrerRole}_pct", 0);

        if ($percentage <= 0) {
            return 0.0;
        }

        $feeKey = "vendor_tier{$vendorTier}_onboarding_fee";
        $onboardingFee = (float) Setting::get($feeKey, 0);

        return round(($percentage / 100) * $onboardingFee, 2);
    }
```

Add the `Setting` import at the top of the file:

```php
use App\Models\Setting;
```

- [ ] **Step 4: Update activateReferral() to use dynamic calculation**

Replace the earning-capable block in `activateReferral()` (lines 157-172) with:

```php
            if ($sharer->isEarningCapable()) {
                // Determine bonus amount: dynamic calculation for new codes,
                // fallback to stored registration_bonus for legacy codes.
                $storedBonus = (float) $referral->referralCode->registration_bonus;

                if ($storedBonus > 0) {
                    $bonusAmount = $storedBonus;
                } else {
                    $vendorTier = $vendorApplication->getVendorTier();
                    $bonusAmount = $this->calculateRegistrationBonus($sharer->role, $vendorTier);
                }

                if ($bonusAmount > 0) {
                    Earning::create([
                        'user_id' => $referral->influencer_id,
                        'user_role' => $sharer->role,
                        'earning_type' => Earning::TYPE_REFERRAL_BONUS,
                        'earnable_id' => $referral->id,
                        'earnable_type' => Referral::class,
                        'amount' => $bonusAmount,
                        'currency' => 'GHS',
                        'status' => Earning::STATUS_PENDING,
                        'description' => "Registration bonus for referring vendor: {$referral->vendor->name}",
                        'earned_at' => now(),
                    ]);
                }
            }
```

Note: The `activateReferral()` method needs access to `$vendorApplication` inside the transaction. Currently the closure only receives `$referral`. Update the transaction closure to also `use ($vendorApplication)`:

Change line 148 from:
```php
        return DB::transaction(function () use ($referral) {
```
to:
```php
        return DB::transaction(function () use ($referral, $vendorApplication) {
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=DynamicRegistrationBonusTest
```

Expected: All 5 tests PASS.

- [ ] **Step 6: Run Pint and commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add app/Services/ReferralService.php tests/Feature/DynamicRegistrationBonusTest.php && git commit -m "feat(referral): add dynamic registration bonus calculation"
```

---

## Task 4: Update ReferralService::createReferralCode() for Prefix Support

**Files:**
- Modify: `app/Services/ReferralService.php`

- [ ] **Step 1: Update createReferralCode signature and implementation**

In `app/Services/ReferralService.php`, update the `createReferralCode()` method. Add a `?string $prefix = null` parameter and use it when creating the code:

```php
    public function createReferralCode(
        User $influencer,
        ?string $code = null,
        ?string $description = null,
        float $registrationBonus = 0.00,
        float $commissionRate = 5.00,
        int $commissionDurationMonths = 3,
        ?int $maxUsages = null,
        ?\DateTime $expiresAt = null,
        ?string $prefix = null
    ): ReferralCode {
        $referralCode = new ReferralCode([
            'influencer_id' => $influencer->id,
            'code' => $code,
            'description' => $description,
            'registration_bonus' => $registrationBonus,
            'commission_rate' => $commissionRate,
            'commission_duration_months' => $commissionDurationMonths,
            'max_usages' => $maxUsages,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
        $referralCode->prefix = $prefix;
        $referralCode->save();

        return $referralCode;
    }
```

Note: the default for `registrationBonus` changes from `50.00` to `0.00` since bonuses are now dynamic. Existing callers that relied on the `50.00` default (API controller) need to be updated — but since bonuses are dynamic now, passing 0 is correct.

- [ ] **Step 2: Run existing tests to check for regressions**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralCodePrefixTest
```

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=DynamicRegistrationBonusTest
```

Expected: All pass.

- [ ] **Step 3: Commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add app/Services/ReferralService.php && git commit -m "feat(referral): add prefix parameter to createReferralCode"
```

---

## Task 5: Bulk Generate Service Method and Request Validation

**Files:**
- Modify: `app/Services/ReferralService.php`
- Create: `app/Http/Requests/BulkGenerateReferralCodeRequest.php`
- Create: `tests/Feature/BulkGenerateReferralCodeTest.php`

- [ ] **Step 1: Create the Form Request**

```bash
cd C:/dev/surprise_moi_backend && php artisan make:request BulkGenerateReferralCodeRequest --no-interaction
```

Replace contents of `app/Http/Requests/BulkGenerateReferralCodeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkGenerateReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:customer,vendor,influencer,field_agent,marketer'],
        ];
    }
}
```

- [ ] **Step 2: Write the failing test**

```bash
cd C:/dev/surprise_moi_backend && php artisan make:test BulkGenerateReferralCodeTest --phpunit --no-interaction
```

Replace contents of `tests/Feature/BulkGenerateReferralCodeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkGenerateReferralCodeTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    public function testBulkGenerateCreatesCodesForUsersWithoutActiveCode(): void
    {
        User::factory()->count(3)->vendor()->create();

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(3, $count);
        $this->assertEquals(3, ReferralCode::where('is_active', true)->count());
    }

    public function testBulkGenerateSkipsUsersWithExistingActiveCode(): void
    {
        $vendorWithCode = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendorWithCode->id,
            'is_active' => true,
        ]);

        User::factory()->count(2)->vendor()->create();

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(2, $count);
    }

    public function testBulkGenerateUsesCorrectPrefixForRole(): void
    {
        User::factory()->vendor()->create();
        User::factory()->influencer()->create();

        $this->service->bulkGenerateCodes('vendor');

        $vendorCode = ReferralCode::latest()->first();
        $this->assertStringStartsWith('VD-', $vendorCode->code);

        $this->service->bulkGenerateCodes('influencer');

        $influencerCode = ReferralCode::latest()->first();
        $this->assertStringStartsWith('IN-', $influencerCode->code);
    }

    public function testBulkGenerateReturnsZeroWhenAllUsersHaveCodes(): void
    {
        $vendor = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendor->id,
            'is_active' => true,
        ]);

        $count = $this->service->bulkGenerateCodes('vendor');

        $this->assertEquals(0, $count);
    }

    public function testBulkGenerateHandlesCustomerRoleIncludingNullRole(): void
    {
        // Customer with explicit role
        User::factory()->create(['role' => 'customer']);
        // Legacy customer with null role
        User::factory()->create(['role' => null]);

        $count = $this->service->bulkGenerateCodes('customer');

        $this->assertEquals(2, $count);

        $codes = ReferralCode::all();
        $codes->each(function ($code) {
            $this->assertStringStartsWith('CU-', $code->code);
        });
    }

    public function testBulkGeneratePreviewEndpointReturnsCorrectCounts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        User::factory()->count(5)->vendor()->create();
        $vendorWithCode = User::factory()->vendor()->create();
        ReferralCode::factory()->create([
            'influencer_id' => $vendorWithCode->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate/preview', [
            'role' => 'vendor',
        ]);

        $response->assertOk()
            ->assertJson([
                'total' => 6,
                'with_code' => 1,
                'without_code' => 5,
                'prefix' => 'VD-',
            ]);
    }

    public function testBulkGenerateEndpointCreatesCodesAndRedirects(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->count(3)->vendor()->create();

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'vendor',
        ]);

        $response->assertOk()
            ->assertJson([
                'generated' => 3,
            ]);

        $this->assertEquals(3, ReferralCode::count());
    }

    public function testBulkGenerateEndpointForbidsNonAdmin(): void
    {
        $vendor = User::factory()->vendor()->create();

        $response = $this->actingAs($vendor)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'vendor',
        ]);

        $response->assertForbidden();
    }

    public function testBulkGenerateEndpointRejectsInvalidRole(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->postJson('/dashboard/referral-codes/bulk-generate', [
            'role' => 'admin',
        ]);

        $response->assertUnprocessable();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=BulkGenerateReferralCodeTest
```

Expected: FAIL — `bulkGenerateCodes` method does not exist and routes not defined.

- [ ] **Step 4: Add bulkGenerateCodes to ReferralService**

In `app/Services/ReferralService.php`, add this method after `createReferralCode()`:

```php
    /**
     * Bulk-generate referral codes for all users of a given role who
     * do not already have an active referral code.
     *
     * @return int Number of codes created
     */
    public function bulkGenerateCodes(string $role): int
    {
        $prefix = ReferralCode::getPrefixForRole($role);

        $query = User::query();
        if ($role === 'customer') {
            $query->where(function ($q) {
                $q->where('role', 'customer')->orWhereNull('role');
            });
        } else {
            $query->where('role', $role);
        }

        // Exclude users who already have an active referral code
        $query->whereDoesntHave('referralCodes', function ($q) {
            $q->where('is_active', true);
        });

        $users = $query->get();

        return DB::transaction(function () use ($users, $prefix) {
            $count = 0;
            foreach ($users as $user) {
                $this->createReferralCode(
                    influencer: $user,
                    description: 'Bulk-generated referral code',
                    prefix: $prefix,
                );
                $count++;
            }

            return $count;
        });
    }
```

This requires that the `User` model has a `referralCodes` relationship. Let's verify and add it if needed. The User model should have:

```php
    public function referralCodes(): HasMany
    {
        return $this->hasMany(ReferralCode::class, 'influencer_id');
    }
```

Check if this exists first. If not, add it to `app/Models/User.php`.

- [ ] **Step 5: Add routes and controller actions**

In `routes/web.php`, add before the `Route::resource('referral-codes', ...)` line (after the `users-by-role` route, around line 116):

```php
    Route::post('referral-codes/bulk-generate/preview', [ReferralCodeController::class, 'bulkGeneratePreview'])
        ->name('referral-codes.bulk-generate.preview');
    Route::post('referral-codes/bulk-generate', [ReferralCodeController::class, 'bulkGenerate'])
        ->name('referral-codes.bulk-generate');
```

In `app/Http/Controllers/ReferralCodeController.php`, add the ReferralService dependency. Add constructor:

```php
    use AuthorizesRequests;

    public function __construct(protected ReferralService $referralService) {}
```

Add the import at the top:

```php
use App\Http\Requests\BulkGenerateReferralCodeRequest;
use App\Services\ReferralService;
```

Add the two new actions:

```php
    public function bulkGeneratePreview(BulkGenerateReferralCodeRequest $request): JsonResponse
    {
        $role = $request->validated('role');

        $query = User::query();
        if ($role === 'customer') {
            $query->where(function ($q) {
                $q->where('role', 'customer')->orWhereNull('role');
            });
        } else {
            $query->where('role', $role);
        }

        $total = $query->count();

        $withCode = (clone $query)->whereHas('referralCodes', function ($q) {
            $q->where('is_active', true);
        })->count();

        return response()->json([
            'total' => $total,
            'with_code' => $withCode,
            'without_code' => $total - $withCode,
            'prefix' => ReferralCode::getPrefixForRole($role),
        ]);
    }

    public function bulkGenerate(BulkGenerateReferralCodeRequest $request): JsonResponse
    {
        $role = $request->validated('role');
        $count = $this->referralService->bulkGenerateCodes($role);

        return response()->json([
            'generated' => $count,
        ]);
    }
```

- [ ] **Step 6: Add referralCodes relationship to User model if missing**

Check if `User` model has a `referralCodes` relationship. If not, add to `app/Models/User.php`:

```php
    /**
     * Get all referral codes created by this user.
     */
    public function referralCodes(): HasMany
    {
        return $this->hasMany(ReferralCode::class, 'influencer_id');
    }
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=BulkGenerateReferralCodeTest
```

Expected: All 9 tests PASS.

- [ ] **Step 8: Run Pint and commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add app/Services/ReferralService.php app/Http/Controllers/ReferralCodeController.php app/Http/Requests/BulkGenerateReferralCodeRequest.php app/Models/User.php routes/web.php && git commit -m "feat(referral): add bulk code generation service, controller, and routes"
```

---

## Task 6: Update Vendor Onboarding Settings — Backend

**Files:**
- Modify: `app/Http/Controllers/Settings/VendorOnboardingController.php`
- Create: `tests/Feature/ReferralBonusSettingsTest.php`

- [ ] **Step 1: Write the failing test**

```bash
cd C:/dev/surprise_moi_backend && php artisan make:test ReferralBonusSettingsTest --phpunit --no-interaction
```

Replace contents of `tests/Feature/ReferralBonusSettingsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralBonusSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function testSettingsPageIncludesReferralBonusPercentages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        Setting::set('referral_bonus_customer_pct', 15.00, 'number');

        $response = $this->actingAs($admin)->get('/settings/vendor-onboarding');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/vendor-onboarding')
            ->has('settings.referral_bonus_customer_pct')
        );
    }

    public function testUpdateSavesReferralBonusPercentages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        // Seed existing tier settings so validation passes
        Setting::set('vendor_tier1_onboarding_fee', 150.00, 'number');
        Setting::set('vendor_tier2_onboarding_fee', 100.00, 'number');
        Setting::set('vendor_tier1_commission_rate', 12.00, 'number');
        Setting::set('vendor_tier2_commission_rate', 8.00, 'number');

        $response = $this->actingAs($admin)->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '150.00',
            'vendor_tier2_onboarding_fee' => '100.00',
            'vendor_tier1_commission_rate' => '12.00',
            'vendor_tier2_commission_rate' => '8.00',
            'referral_bonus_customer_pct' => '18.00',
            'referral_bonus_vendor_pct' => '22.00',
            'referral_bonus_influencer_pct' => '28.00',
            'referral_bonus_field_agent_pct' => '35.00',
            'referral_bonus_marketer_pct' => '25.00',
        ]);

        $response->assertRedirect();
        $this->assertEquals(18.00, Setting::get('referral_bonus_customer_pct'));
        $this->assertEquals(22.00, Setting::get('referral_bonus_vendor_pct'));
        $this->assertEquals(28.00, Setting::get('referral_bonus_influencer_pct'));
        $this->assertEquals(35.00, Setting::get('referral_bonus_field_agent_pct'));
        $this->assertEquals(25.00, Setting::get('referral_bonus_marketer_pct'));
    }

    public function testUpdateRejectsPercentageOver100(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->post('/settings/vendor-onboarding', [
            'vendor_tier1_onboarding_fee' => '150.00',
            'vendor_tier2_onboarding_fee' => '100.00',
            'vendor_tier1_commission_rate' => '12.00',
            'vendor_tier2_commission_rate' => '8.00',
            'referral_bonus_customer_pct' => '150.00',
            'referral_bonus_vendor_pct' => '20.00',
            'referral_bonus_influencer_pct' => '25.00',
            'referral_bonus_field_agent_pct' => '30.00',
            'referral_bonus_marketer_pct' => '20.00',
        ]);

        $response->assertSessionHasErrors('referral_bonus_customer_pct');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralBonusSettingsTest
```

Expected: FAIL — settings page doesn't include percentage data, update doesn't validate them.

- [ ] **Step 3: Update VendorOnboardingController**

In `app/Http/Controllers/Settings/VendorOnboardingController.php`, update the `update()` method to include the 5 new fields:

```php
    public function update(Request $request)
    {
        $this->authorize('updateAny', Setting::class);

        $validated = $request->validate([
            'vendor_tier1_onboarding_fee' => 'required|numeric|min:0',
            'vendor_tier2_onboarding_fee' => 'required|numeric|min:0',
            'vendor_tier1_commission_rate' => 'required|numeric|min:0|max:100',
            'vendor_tier2_commission_rate' => 'required|numeric|min:0|max:100',
            'referral_bonus_customer_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_vendor_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_influencer_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_field_agent_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_marketer_pct' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value, 'number');
        }

        return back()->with('success', 'Settings updated successfully.');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralBonusSettingsTest
```

Expected: All 3 tests PASS.

- [ ] **Step 5: Run Pint and commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add app/Http/Controllers/Settings/VendorOnboardingController.php tests/Feature/ReferralBonusSettingsTest.php && git commit -m "feat(referral): add referral bonus percentage settings to vendor onboarding controller"
```

---

## Task 7: Update Vendor Onboarding Settings — Frontend

**Files:**
- Modify: `resources/js/pages/settings/vendor-onboarding.tsx`

- [ ] **Step 1: Update the Settings interface**

In `resources/js/pages/settings/vendor-onboarding.tsx`, extend the `Settings` interface to include the 5 new fields:

```typescript
interface Settings {
    vendor_tier1_onboarding_fee?: { value: string; type: string; description: string };
    vendor_tier2_onboarding_fee?: { value: string; type: string; description: string };
    vendor_tier1_commission_rate?: { value: string; type: string; description: string };
    vendor_tier2_commission_rate?: { value: string; type: string; description: string };
    referral_bonus_customer_pct?: { value: string; type: string; description: string };
    referral_bonus_vendor_pct?: { value: string; type: string; description: string };
    referral_bonus_influencer_pct?: { value: string; type: string; description: string };
    referral_bonus_field_agent_pct?: { value: string; type: string; description: string };
    referral_bonus_marketer_pct?: { value: string; type: string; description: string };
}
```

- [ ] **Step 2: Add the Referral Bonus Percentages section**

Add `import { useState } from 'react';` to the imports.

After the Tier 2 Card (before the Save button `Box`), add a new Card for referral bonus percentages. Inside the `Form` render function, after the Tier 2 `</Card>`:

```tsx
                                {/* Referral Bonus Percentages */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Referral Bonus Percentages</CardTitle>
                                        <CardDescription>
                                            Set the registration bonus percentage for each user category. The bonus is calculated as a percentage of the referred person's tier onboarding fee.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ReferralBonusFields
                                            settings={settings}
                                            errors={errors}
                                        />
                                    </CardContent>
                                </Card>
```

- [ ] **Step 3: Create the ReferralBonusFields component**

Add this component above the default export in the same file:

```tsx
const BONUS_CATEGORIES = [
    { key: 'referral_bonus_customer_pct', label: 'Customer', defaultValue: '15.00' },
    { key: 'referral_bonus_vendor_pct', label: 'Vendor', defaultValue: '20.00' },
    { key: 'referral_bonus_influencer_pct', label: 'Influencer', defaultValue: '25.00' },
    { key: 'referral_bonus_field_agent_pct', label: 'Field Agent', defaultValue: '30.00' },
    { key: 'referral_bonus_marketer_pct', label: 'Marketer', defaultValue: '20.00' },
] as const;

function ReferralBonusFields({
    settings,
    errors,
}: {
    settings: Settings;
    errors: Record<string, string>;
}) {
    const tier1Fee = parseFloat(settings.vendor_tier1_onboarding_fee?.value || '150');
    const tier2Fee = parseFloat(settings.vendor_tier2_onboarding_fee?.value || '100');

    const [percentages, setPercentages] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};
        for (const cat of BONUS_CATEGORIES) {
            initial[cat.key] = settings[cat.key as keyof Settings]?.value || cat.defaultValue;
        }
        return initial;
    });

    const computeBonus = (pctStr: string, fee: number) => {
        const pct = parseFloat(pctStr);
        if (isNaN(pct) || isNaN(fee)) return '0.00';
        return ((pct / 100) * fee).toFixed(2);
    };

    return (
        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
            {BONUS_CATEGORIES.map((cat) => (
                <Box key={cat.key} sx={{ display: 'grid', gap: 1 }}>
                    <Label htmlFor={cat.key}>{cat.label} (%)</Label>
                    <Input
                        id={cat.key}
                        name={cat.key}
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={percentages[cat.key]}
                        onChange={(e) =>
                            setPercentages((prev) => ({
                                ...prev,
                                [cat.key]: e.target.value,
                            }))
                        }
                        required
                    />
                    <Typography variant="body2" color="text.secondary">
                        Tier 1: GH₵{computeBonus(percentages[cat.key], tier1Fee)} | Tier 2: GH₵{computeBonus(percentages[cat.key], tier2Fee)}
                    </Typography>
                    <InputError message={errors[cat.key]} />
                </Box>
            ))}
        </Box>
    );
}
```

- [ ] **Step 4: Verify the page renders**

Start the dev server if not running:

```bash
cd C:/dev/surprise_moi_backend && pnpm run dev
```

Navigate to `/settings/vendor-onboarding` in the browser. Verify:
- The new Referral Bonus Percentages card appears below the Tier 2 card
- Each category shows a percentage input with live Tier 1 / Tier 2 GHS calculation
- Changing a percentage updates the GHS values immediately

- [ ] **Step 5: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add resources/js/pages/settings/vendor-onboarding.tsx && git commit -m "feat(referral): add referral bonus percentage inputs to vendor onboarding settings page"
```

---

## Task 8: Update Referral Codes Index Page — Bulk Generate Modal

**Files:**
- Modify: `resources/js/pages/referral-codes/index.tsx`

- [ ] **Step 1: Add the BulkGenerateModal component and state**

Add `useState` to the React import. Add after the existing imports:

```tsx
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Users } from 'lucide-react';
```

Add the `BULK_CATEGORIES` constant and the modal component before the default export:

```tsx
const BULK_CATEGORIES: Array<{ label: string; value: string }> = [
    { label: 'Customer', value: 'customer' },
    { label: 'Vendor', value: 'vendor' },
    { label: 'Influencer', value: 'influencer' },
    { label: 'Field Agent', value: 'field_agent' },
    { label: 'Marketer', value: 'marketer' },
];

interface PreviewData {
    total: number;
    with_code: number;
    without_code: number;
    prefix: string;
}

function BulkGenerateModal({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const [role, setRole] = useState('');
    const [preview, setPreview] = useState<PreviewData | null>(null);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [result, setResult] = useState<number | null>(null);

    const fetchPreview = async (selectedRole: string) => {
        setLoading(true);
        setPreview(null);
        try {
            const res = await fetch('/dashboard/referral-codes/bulk-generate/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ role: selectedRole }),
            });
            const data = await res.json();
            setPreview(data);
        } catch {
            // silently fail
        } finally {
            setLoading(false);
        }
    };

    const handleRoleChange = (value: string) => {
        setRole(value);
        setResult(null);
        fetchPreview(value);
    };

    const handleGenerate = async () => {
        setGenerating(true);
        try {
            const res = await fetch('/dashboard/referral-codes/bulk-generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ role }),
            });
            const data = await res.json();
            setResult(data.generated);
        } catch {
            // silently fail
        } finally {
            setGenerating(false);
        }
    };

    const handleClose = () => {
        if (result !== null) {
            router.reload();
        }
        setRole('');
        setPreview(null);
        setResult(null);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bulk Generate Referral Codes</DialogTitle>
                    <DialogDescription>
                        Generate codes for all users in a category who don't have an active code.
                    </DialogDescription>
                </DialogHeader>

                {result !== null ? (
                    <Box sx={{ textAlign: 'center', py: 3 }}>
                        <Typography variant="h4" sx={{ fontWeight: 700, mb: 1 }}>
                            {result} Codes Generated
                        </Typography>
                        <Typography color="text.secondary" sx={{ mb: 3 }}>
                            All {BULK_CATEGORIES.find((c) => c.value === role)?.label} users without an active code now have a referral code.
                        </Typography>
                        <Button onClick={handleClose}>Done</Button>
                    </Box>
                ) : (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box sx={{ display: 'grid', gap: 1 }}>
                            <Typography variant="body2" sx={{ fontWeight: 500 }}>
                                User Category
                            </Typography>
                            <Select value={role} onValueChange={handleRoleChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a category..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {BULK_CATEGORIES.map((cat) => (
                                        <SelectItem key={cat.value} value={cat.value}>
                                            {cat.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Box>

                        {loading && (
                            <Typography variant="body2" color="text.secondary">
                                Loading preview...
                            </Typography>
                        )}

                        {preview && !loading && (
                            <Box
                                sx={{
                                    p: 2,
                                    borderRadius: 1,
                                    bgcolor: 'action.hover',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 1,
                                }}
                            >
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        Total users
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                        {preview.total}
                                    </Typography>
                                </Box>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        Already have active code
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'warning.main' }}>
                                        {preview.with_code}
                                    </Typography>
                                </Box>
                                <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 1, display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                        Codes to generate
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 700, color: 'success.main' }}>
                                        {preview.without_code}
                                    </Typography>
                                </Box>
                                <Typography variant="caption" color="text.secondary">
                                    Code format: {preview.prefix}XXXXXXXX
                                </Typography>
                            </Box>
                        )}

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleGenerate}
                                disabled={!preview || preview.without_code === 0 || generating}
                            >
                                {generating
                                    ? 'Generating...'
                                    : preview
                                      ? `Generate ${preview.without_code} Codes`
                                      : 'Generate'}
                            </Button>
                        </Box>
                    </Box>
                )}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Add the Bulk Generate button and modal state to the index page**

In the `ReferralCodesIndex` component, add state:

```tsx
    const [bulkModalOpen, setBulkModalOpen] = useState(false);
```

Update the header buttons area. Replace the existing `<Button asChild>` block with:

```tsx
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        <Button variant="outline" onClick={() => setBulkModalOpen(true)}>
                            <Users style={{ marginRight: 8, width: 16, height: 16 }} />
                            Bulk Generate
                        </Button>
                        <Button asChild>
                            <Link href="/dashboard/referral-codes/create">
                                <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                Create Code
                            </Link>
                        </Button>
                    </Box>
```

Add the modal at the end of the component, before the closing `</AppLayout>`:

```tsx
                <BulkGenerateModal
                    open={bulkModalOpen}
                    onClose={() => setBulkModalOpen(false)}
                />
```

Add `useState` to the React imports if not already present (add to the existing `lucide-react` import for `Users`).

- [ ] **Step 3: Verify in browser**

Navigate to `/dashboard/referral-codes` in the browser. Verify:
- "Bulk Generate" button appears next to "Create Code"
- Clicking it opens the modal
- Selecting a category shows the preview with correct counts
- The generate button shows the correct count
- After generating, the success state shows and closing reloads the page

- [ ] **Step 4: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add resources/js/pages/referral-codes/index.tsx && git commit -m "feat(referral): add bulk generate modal to referral codes index page"
```

---

## Task 9: Update Create Form — Remove Manual Bonus Input

**Files:**
- Modify: `resources/js/pages/referral-codes/create.tsx`
- Modify: `app/Http/Controllers/ReferralCodeController.php`

- [ ] **Step 1: Update the backend store action**

In `app/Http/Controllers/ReferralCodeController.php`, update `store()` to use the prefix and remove `registration_bonus` from validation:

```php
    public function store(Request $request)
    {
        $this->authorize('create', ReferralCode::class);

        $validated = $request->validate([
            'influencer_id' => 'required|exists:users,id',
            'description' => 'nullable|string|max:255',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $user = User::findOrFail($validated['influencer_id']);
        $prefix = ReferralCode::getPrefixForRole($user->role ?? 'customer');

        $this->referralService->createReferralCode(
            influencer: $user,
            description: $validated['description'] ?? null,
            maxUsages: $validated['max_usages'] ?? null,
            expiresAt: isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
            prefix: $prefix,
        );

        return redirect()->route('referral-codes.index')
            ->with('success', 'Referral code created successfully.');
    }
```

- [ ] **Step 2: Update the frontend create form**

In `resources/js/pages/referral-codes/create.tsx`, remove the Registration Bonus input block (lines 237-255 approximately — the entire `Box` containing the `registration_bonus` input, label, and error).

In its place, add a read-only bonus preview that shows computed values based on the selected category. Add state and a helper:

After the existing `useState` declarations, add:

```tsx
    const tier1Fee = 150; // These will be passed as props in a future enhancement
    const tier2Fee = 100;

    const ROLE_BONUS_DEFAULTS: Record<string, number> = {
        customer: 15,
        vendor: 20,
        influencer: 25,
        field_agent: 30,
        marketer: 20,
        admin: 0,
        super_admin: 0,
    };

    const selectedPct = ROLE_BONUS_DEFAULTS[selectedCategory] ?? 0;
    const tier1Bonus = ((selectedPct / 100) * tier1Fee).toFixed(2);
    const tier2Bonus = ((selectedPct / 100) * tier2Fee).toFixed(2);
```

Replace the removed registration_bonus block with:

```tsx
                                {selectedCategory && (
                                    <Box
                                        sx={{
                                            p: 2,
                                            borderRadius: 1,
                                            bgcolor: 'action.hover',
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 0.5,
                                        }}
                                    >
                                        <Typography variant="body2" sx={{ fontWeight: 500 }}>
                                            Registration Bonus (auto-calculated)
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            Tier 1: GH₵{tier1Bonus} | Tier 2: GH₵{tier2Bonus}
                                        </Typography>
                                        <Typography variant="caption" color="text.secondary">
                                            Based on {selectedPct}% of onboarding fee for{' '}
                                            {USER_CATEGORIES.find((c) => c.value === selectedCategory)?.label}
                                        </Typography>
                                    </Box>
                                )}
```

- [ ] **Step 3: Verify in browser**

Navigate to `/dashboard/referral-codes/create`. Verify:
- The manual Registration Bonus input is gone
- Selecting a category shows the computed bonus preview
- Creating a code works without the bonus field

- [ ] **Step 4: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add app/Http/Controllers/ReferralCodeController.php resources/js/pages/referral-codes/create.tsx && git commit -m "feat(referral): replace manual bonus input with dynamic preview on create form"
```

---

## Task 10: Update API Controllers for Prefix Support

**Files:**
- Modify: `app/Http/Controllers/Api/V1/ReferralCodeController.php`
- Modify: `app/Http/Controllers/Api/V1/MyReferralController.php`
- Modify: `app/Http/Requests/StoreReferralCodeRequest.php`

- [ ] **Step 1: Update StoreReferralCodeRequest — remove registration_bonus**

In `app/Http/Requests/StoreReferralCodeRequest.php`, remove the `'registration_bonus'` rule and its custom message:

```php
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', 'unique:referral_codes,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'max_usages' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This referral code is already taken.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
```

- [ ] **Step 2: Update API ReferralCodeController::store()**

In `app/Http/Controllers/Api/V1/ReferralCodeController.php`, update `store()` to pass the user's role prefix:

```php
    public function store(StoreReferralCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $prefix = ReferralCode::getPrefixForRole($user->role ?? 'customer');

        $code = $this->referralService->createReferralCode(
            influencer: $user,
            code: $request->input('code'),
            description: $request->input('description'),
            maxUsages: $request->input('max_usages'),
            expiresAt: $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null,
            prefix: $prefix,
        );

        return response()->json([
            'success' => true,
            'message' => 'Referral code created successfully.',
            'data' => new ReferralCodeResource($code->load('influencer')),
        ], 201);
    }
```

Add the import at top:

```php
use App\Models\ReferralCode;
```

- [ ] **Step 3: Update MyReferralController::show()**

In `app/Http/Controllers/Api/V1/MyReferralController.php`, update the auto-creation block (lines 39-44) to pass the prefix:

```php
        if (! $referralCode) {
            $prefix = ReferralCode::getPrefixForRole($user->role ?? 'customer');
            $referralCode = $this->referralService->createReferralCode(
                influencer: $user,
                description: 'Personal referral code',
                prefix: $prefix,
            );
        }
```

Add the import at top:

```php
use App\Models\ReferralCode;
```

Wait — `ReferralCode` is already imported in this file. Just add the prefix logic.

Also, add computed bonus info to the response. After the existing `'milestone_increment' => $increment,` line, add:

```php
                'registration_bonus_tier1' => $this->referralService->calculateRegistrationBonus($user->role ?? 'customer', 1),
                'registration_bonus_tier2' => $this->referralService->calculateRegistrationBonus($user->role ?? 'customer', 2),
```

- [ ] **Step 4: Run existing tests to check for regressions**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=ReferralCode
```

Expected: All pass.

- [ ] **Step 5: Run Pint and commit**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent && git add app/Http/Controllers/Api/V1/ReferralCodeController.php app/Http/Controllers/Api/V1/MyReferralController.php app/Http/Requests/StoreReferralCodeRequest.php && git commit -m "feat(referral): add prefix support to API controllers and include dynamic bonus in mobile response"
```

---

## Task 11: Update Referral Codes Index — Bonus Column Display

**Files:**
- Modify: `resources/js/pages/referral-codes/index.tsx`

- [ ] **Step 1: Update the bonus column to show dynamic values**

In the referral codes index table, find the Bonus table cell (the `<Box component="td">` containing `GH₵{code.registration_bonus}`).

Replace:

```tsx
                                            <Box component="td" sx={{ p: 1 }}>
                                                GH₵{code.registration_bonus}
                                            </Box>
```

With:

```tsx
                                            <Box component="td" sx={{ p: 1 }}>
                                                {Number(code.registration_bonus) > 0 ? (
                                                    <>GH₵{code.registration_bonus}</>
                                                ) : (
                                                    <Typography variant="body2" color="text.secondary" sx={{ fontSize: '0.8rem' }}>
                                                        Dynamic
                                                    </Typography>
                                                )}
                                            </Box>
```

- [ ] **Step 2: Verify in browser**

Navigate to `/dashboard/referral-codes`. Verify:
- Legacy codes with a stored bonus show "GH₵50.00" (or whatever their value is)
- New codes with 0 bonus show "Dynamic"

- [ ] **Step 3: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add resources/js/pages/referral-codes/index.tsx && git commit -m "feat(referral): show dynamic label for codes using percentage-based bonus"
```

---

## Task 12: Update Edit Form — Remove Manual Bonus Input

**Files:**
- Modify: `resources/js/pages/referral-codes/edit.tsx`
- Modify: `app/Http/Controllers/ReferralCodeController.php`

- [ ] **Step 1: Update backend update action**

In `app/Http/Controllers/ReferralCodeController.php`, update the `update()` method to remove `registration_bonus` from validation:

```php
    public function update(Request $request, ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $referralCode->update($validated);

        return redirect()->route('referral-codes.index')
            ->with('success', 'Referral code updated successfully.');
    }
```

- [ ] **Step 2: Update the frontend edit form**

Read `resources/js/pages/referral-codes/edit.tsx` first, then remove the `registration_bonus` input field and replace it with a read-only info box showing the code's bonus status (legacy value or "Dynamic - based on category percentage"). The exact edit depends on the file's current contents.

- [ ] **Step 3: Verify in browser**

Navigate to edit a referral code. Verify:
- The manual Registration Bonus field is gone
- The form submits successfully without it

- [ ] **Step 4: Commit**

```bash
cd C:/dev/surprise_moi_backend && git add app/Http/Controllers/ReferralCodeController.php resources/js/pages/referral-codes/edit.tsx && git commit -m "feat(referral): remove manual bonus input from edit form"
```

---

## Task 13: Run Full Test Suite

- [ ] **Step 1: Run all referral-related tests**

```bash
cd C:/dev/surprise_moi_backend && php artisan test --compact --filter=Referral
```

Expected: All pass.

- [ ] **Step 2: Run Pint on all modified files**

```bash
cd C:/dev/surprise_moi_backend && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Ask the user if they want to run the full test suite**

Ask: "All referral tests pass. Would you like me to run the full test suite to check for regressions?"
