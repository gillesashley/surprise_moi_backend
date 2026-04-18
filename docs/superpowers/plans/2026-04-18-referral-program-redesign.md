# Referral Program Redesign — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Phase 1 of the referral program redesign — add a vendor-onboarding subsidy, unify every role onto a single points lane, and remove the legacy commission/milestone branching.

**Architecture:** Extend existing `settings` table with 3 new rows (subsidy %, points-per-GHS, cashout-min). Route vendor onboarding discounts through `ReferralCode` + subsidy setting (coupon model already disentangled). Rewrite `ReferralService::activateReferral` to always award points against `vendor_applications.final_amount`. Freeze legacy milestone + earning rows (no writes, no deletions).

**Tech Stack:** Laravel 12, PHP 8.2, Inertia + React 19 + Material UI (admin), PHPUnit 11. Paystack integration untouched.

**Spec:** `docs/superpowers/specs/2026-04-18-referral-program-redesign-design.md`

---

## File Structure

### New files

- `database/migrations/2026_04_18_120000_seed_referral_program_redesign_settings.php` — seeds the 3 new settings rows.
- `database/migrations/2026_04_18_120100_add_referral_code_id_to_vendor_onboarding_payments_table.php` — adds the FK column.

### Modified files

| Path | Why |
|---|---|
| `app/Models/VendorApplication.php` | Rewrite `calculateFinalAmount()` — take `?ReferralCode` instead of `?Coupon`; apply subsidy. |
| `app/Models/VendorOnboardingPayment.php` | Add `referral_code_id` to `$fillable`, add `referralCode()` relation. |
| `app/Services/VendorOnboardingPaymentService.php` | `validateReferralCode` — compute subsidy in return. `initializePayment` — pass referral code to `calculateFinalAmount` and persist `referral_code_id` on payment row. |
| `app/Services/ReferralService.php` | Rewrite `activateReferral` (points-only, no role branching). Simplify `awardPoints` (no milestone check). Delete `calculateRegistrationBonus`, `checkMilestones`, `getMilestoneThresholds`. Add defence-in-depth self-referral guard in `applyReferralCode`. |
| `app/Http/Controllers/Settings/VendorOnboardingController.php` | Validation for 3 new setting keys. |
| `resources/js/pages/settings/vendor-onboarding.tsx` | 2 new cards (Vendor Subsidy, Referral Points System). Update helper text on Referral Bonus Percentages card. |
| `config/referral.php` | Delete obsolete `milestone_first`, `milestone_increment`, `points_per_vendor_onboarding`. |
| `tests/Unit/Services/ReferralServiceTest.php` | All roles award points; no earnings, no milestone rows. |
| `tests/Feature/DynamicRegistrationBonusTest.php` | Base = `final_amount` post-subsidy. |
| `tests/Feature/Settings/VendorOnboardingSettingsTest.php` | Assert new settings fields save and validate. |
| `tests/Feature/Http/ReferralMilestoneRewardControllerTest.php` | Assert no new milestone rows are created by the referral flow. |

### Conventions to respect

- Laravel 12 structure: casts in `casts()` method, no `app/Http/Kernel.php`.
- Use `config('app.name')` not `env(...)`.
- Factories + PHPUnit (not Pest). Run `php artisan test --compact --filter=...`.
- Run `vendor/bin/pint --dirty --format agent` at the end of every task that edits PHP.
- All commands assume you're running inside the app container (project uses Docker — adapt if working locally without Sail/Docker).

---

## Task 1: Seed 3 new settings via migration

**Files:**
- Create: `database/migrations/2026_04_18_120000_seed_referral_program_redesign_settings.php`
- Modify: `tests/Feature/Settings/VendorOnboardingSettingsTest.php`

- [ ] **Step 1: Write the failing test**

Append this test method to `tests/Feature/Settings/VendorOnboardingSettingsTest.php`:

```php
/** @test */
public function migration_seeds_the_three_new_referral_program_settings(): void
{
    $this->assertSame('25.00', \App\Models\Setting::get('vendor_onboarding_subsidy_pct'));
    $this->assertSame('10', \App\Models\Setting::get('referral_points_per_ghs'));
    $this->assertSame('1000', \App\Models\Setting::get('referral_cashout_min_points'));
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=migration_seeds_the_three_new_referral_program_settings`

Expected: fails — the setting keys don't exist yet.

- [ ] **Step 3: Create the migration**

Write `database/migrations/2026_04_18_120000_seed_referral_program_redesign_settings.php`:

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
                'key' => 'vendor_onboarding_subsidy_pct',
                'value' => '25.00',
                'type' => 'number',
                'description' => "The discount applied to a vendor's onboarding fee when they onboard using a valid referral code. Applies identically to Tier 1 and Tier 2. Example: at 25%, a Tier 1 vendor with a 200 GHS fee pays 150 GHS.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_points_per_ghs',
                'value' => '10',
                'type' => 'number',
                'description' => 'How many points a referrer sees in their wallet for every 1 GHS earned. A higher number makes the reward feel larger. Example: at 10, a 15 GHS reward displays as 150 points.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_cashout_min_points',
                'value' => '1000',
                'type' => 'number',
                'description' => 'The lowest points balance at which a referrer can request a cashout. Example: at 1000 points (= 100 GHS at the current conversion rate), a referrer must reach this balance before they can withdraw.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'vendor_onboarding_subsidy_pct',
            'referral_points_per_ghs',
            'referral_cashout_min_points',
        ])->delete();
    }
};
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=migration_seeds_the_three_new_referral_program_settings`

Expected: pass.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_18_120000_seed_referral_program_redesign_settings.php tests/Feature/Settings/VendorOnboardingSettingsTest.php
git commit -m "feat(referral): seed subsidy, points-per-ghs, and cashout-min settings"
```

---

## Task 2: Add `referral_code_id` to `vendor_onboarding_payments`

**Files:**
- Create: `database/migrations/2026_04_18_120100_add_referral_code_id_to_vendor_onboarding_payments_table.php`
- Modify: `app/Models/VendorOnboardingPayment.php`
- Test: new method inside an existing `tests/Feature/` test or a new `tests/Unit/Models/VendorOnboardingPaymentTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/VendorOnboardingPaymentTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_persists_a_referral_code_id_and_loads_the_relation(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()->create(['user_id' => $user->id]);
        $code = ReferralCode::factory()->create();

        $payment = VendorOnboardingPayment::create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
            'referral_code_id' => $code->id,
            'reference' => VendorOnboardingPayment::generateReference(),
            'amount' => 150.00,
            'amount_in_kobo' => 15000,
            'discount_amount' => 50.00,
            'currency' => 'GHS',
            'status' => VendorOnboardingPayment::STATUS_PENDING,
        ]);

        $this->assertSame($code->id, $payment->fresh()->referral_code_id);
        $this->assertInstanceOf(ReferralCode::class, $payment->referralCode);
        $this->assertSame($code->id, $payment->referralCode->id);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=VendorOnboardingPaymentTest`

Expected: fails — `referral_code_id` column doesn't exist and relation isn't defined.

- [ ] **Step 3: Create the migration**

Write `database/migrations/2026_04_18_120100_add_referral_code_id_to_vendor_onboarding_payments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_onboarding_payments', function (Blueprint $table) {
            $table->foreignId('referral_code_id')
                ->nullable()
                ->after('vendor_application_id')
                ->constrained('referral_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_onboarding_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_code_id');
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/VendorOnboardingPayment.php`, add `'referral_code_id'` to `$fillable` immediately after `'vendor_application_id'`, and add a `referralCode()` relation alongside the existing `coupon()`:

```php
// in $fillable, add right after 'vendor_application_id':
'referral_code_id',
```

```php
// add this method after coupon():
public function referralCode(): BelongsTo
{
    return $this->belongsTo(ReferralCode::class);
}
```

Import `ReferralCode` at the top of the file:

```php
// add to use statements:
use App\Models\ReferralCode;
```

- [ ] **Step 5: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=VendorOnboardingPaymentTest`

Expected: pass.

- [ ] **Step 6: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_04_18_120100_add_referral_code_id_to_vendor_onboarding_payments_table.php app/Models/VendorOnboardingPayment.php tests/Unit/Models/VendorOnboardingPaymentTest.php
git commit -m "feat(referral): link vendor_onboarding_payments to referral_codes"
```

---

## Task 3: Rewrite `VendorApplication::calculateFinalAmount` to apply subsidy

**Files:**
- Modify: `app/Models/VendorApplication.php` (method at ~line 175)
- Test: `tests/Unit/Models/VendorApplicationTest.php` (create if missing, otherwise append)

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Models/VendorApplicationTest.php` (create the file if missing, with standard namespace and `RefreshDatabase`):

```php
/** @test */
public function calculate_final_amount_without_referral_code_returns_full_fee(): void
{
    \App\Models\Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
    $application = \App\Models\VendorApplication::factory()->tier1()->create();

    $amounts = $application->calculateFinalAmount(null);

    $this->assertSame(200.0, $amounts['onboarding_fee']);
    $this->assertSame(0.0, $amounts['discount_amount']);
    $this->assertSame(200.0, $amounts['final_amount']);
}

/** @test */
public function calculate_final_amount_with_referral_code_applies_subsidy(): void
{
    \App\Models\Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
    \App\Models\Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');
    $application = \App\Models\VendorApplication::factory()->tier1()->create();
    $code = \App\Models\ReferralCode::factory()->create();

    $amounts = $application->calculateFinalAmount($code);

    $this->assertSame(200.0, $amounts['onboarding_fee']);
    $this->assertSame(50.0, $amounts['discount_amount']);
    $this->assertSame(150.0, $amounts['final_amount']);
}

/** @test */
public function calculate_final_amount_ignores_invalid_referral_code(): void
{
    \App\Models\Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
    \App\Models\Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');
    $application = \App\Models\VendorApplication::factory()->tier1()->create();
    $code = \App\Models\ReferralCode::factory()->create(['is_active' => false]);

    $amounts = $application->calculateFinalAmount($code);

    $this->assertSame(0.0, $amounts['discount_amount']);
    $this->assertSame(200.0, $amounts['final_amount']);
}
```

(If `VendorApplicationFactory::tier1()` state doesn't exist, add it or replace with the attributes needed to make `getVendorTier()` return 1 — check the existing factory for conventions.)

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=VendorApplicationTest`

Expected: fails — method still takes `?Coupon` and applies coupon discount, not subsidy.

- [ ] **Step 3: Rewrite the method**

In `app/Models/VendorApplication.php`, replace the existing `calculateFinalAmount` and its `use App\Models\Coupon;` import.

Remove this import (if present):
```php
use App\Models\Coupon;
```

Add these imports:
```php
use App\Models\ReferralCode;
use App\Models\Setting;
```

Replace the method:

```php
/**
 * Calculate final amount after applying any referral-code subsidy.
 *
 * When a valid referral code is supplied, the vendor's onboarding fee is
 * reduced by the platform-wide subsidy percentage from settings. This is
 * the sole discount mechanism for vendor onboarding — coupons apply only
 * to cart/product purchases.
 *
 * @return array{onboarding_fee: float, discount_amount: float, final_amount: float}
 */
public function calculateFinalAmount(?ReferralCode $referralCode = null): array
{
    $onboardingFee = $this->getOnboardingFee();
    $discountAmount = 0.0;

    if ($referralCode && $referralCode->isValid()) {
        $subsidyPct = (float) Setting::get('vendor_onboarding_subsidy_pct', 0);
        $discountAmount = round(($subsidyPct / 100) * $onboardingFee, 2);
    }

    $finalAmount = max(0.0, $onboardingFee - $discountAmount);

    return [
        'onboarding_fee' => (float) $onboardingFee,
        'discount_amount' => (float) $discountAmount,
        'final_amount' => (float) $finalAmount,
    ];
}
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=VendorApplicationTest`

Expected: pass.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorApplication.php tests/Unit/Models/VendorApplicationTest.php
git commit -m "feat(referral): apply subsidy from settings in calculateFinalAmount"
```

---

## Task 4: `validateReferralCode` returns subsidy-adjusted amounts

**Files:**
- Modify: `app/Services/VendorOnboardingPaymentService.php` (method at ~line 46)
- Test: create `tests/Unit/Services/VendorOnboardingPaymentServiceTest.php` (or extend if it exists)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/VendorOnboardingPaymentServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function validate_referral_code_returns_subsidy_adjusted_amounts(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
        Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');

        $vendor = User::factory()->create();
        $application = VendorApplication::factory()->tier1()->create(['user_id' => $vendor->id]);
        $referrer = User::factory()->create();
        $code = ReferralCode::factory()->create(['influencer_id' => $referrer->id]);

        $service = $this->app->make(VendorOnboardingPaymentService::class);
        $result = $service->validateReferralCode($code->code, $application);

        $this->assertTrue($result['valid']);
        $this->assertSame(200.0, (float) $result['onboarding_fee']);
        $this->assertSame(50.0, (float) $result['discount_amount']);
        $this->assertSame(150.0, (float) $result['final_amount']);
    }

    /** @test */
    public function validate_referral_code_rejects_self_referral(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');

        $user = User::factory()->create();
        $application = VendorApplication::factory()->tier1()->create(['user_id' => $user->id]);
        $code = ReferralCode::factory()->create(['influencer_id' => $user->id]);

        $service = $this->app->make(VendorOnboardingPaymentService::class);
        $result = $service->validateReferralCode($code->code, $application);

        $this->assertFalse($result['valid']);
        $this->assertSame('You cannot use your own referral code.', $result['message']);
    }

    /** @test */
    public function validate_referral_code_rejects_invalid_code(): void
    {
        Setting::set('vendor_tier1_onboarding_fee', '200', 'number');

        $application = VendorApplication::factory()->tier1()->create();
        $service = $this->app->make(VendorOnboardingPaymentService::class);

        $result = $service->validateReferralCode('BOGUS-CODE', $application);

        $this->assertFalse($result['valid']);
    }
}
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test --compact --filter=VendorOnboardingPaymentServiceTest`

Expected: `validate_referral_code_returns_subsidy_adjusted_amounts` fails because current return gives `discount_amount => 0.0`. The other two pass (already implemented).

- [ ] **Step 3: Update `validateReferralCode`**

In `app/Services/VendorOnboardingPaymentService.php`, replace the bottom of the method (from `$onboardingFee = $application->getOnboardingFee();` through the `return`):

```php
$amounts = $application->calculateFinalAmount($referralCode);

return [
    'valid' => true,
    'referral_code' => $referralCode,
    'onboarding_fee' => $amounts['onboarding_fee'],
    'discount_amount' => $amounts['discount_amount'],
    'final_amount' => $amounts['final_amount'],
    'message' => 'Referral code applied successfully.',
];
```

Also update the docblock above the method — remove the line "Referral codes do not discount the onboarding fee" and replace with:

```
/**
 * Validate a referral code for vendor onboarding.
 *
 * When the code is valid, the returned amounts reflect the platform-wide
 * subsidy applied to the vendor's onboarding fee. The code is also tracked
 * on the application so the referrer can be rewarded with points on
 * approval.
 */
```

- [ ] **Step 4: Run the tests and confirm all three pass**

Run: `php artisan test --compact --filter=VendorOnboardingPaymentServiceTest`

Expected: 3 passes.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/VendorOnboardingPaymentService.php tests/Unit/Services/VendorOnboardingPaymentServiceTest.php
git commit -m "feat(referral): apply subsidy in validateReferralCode return"
```

---

## Task 5: `initializePayment` passes referral code to `calculateFinalAmount` and persists `referral_code_id`

**Files:**
- Modify: `app/Services/VendorOnboardingPaymentService.php` (method at ~line 92)
- Test: append to `tests/Unit/Services/VendorOnboardingPaymentServiceTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Services/VendorOnboardingPaymentServiceTest.php`:

```php
/** @test */
public function initialize_payment_persists_referral_code_id_and_final_amount(): void
{
    Setting::set('vendor_tier1_onboarding_fee', '200', 'number');
    Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');

    $vendor = User::factory()->create();
    $application = VendorApplication::factory()->tier1()->paymentReady()->create(['user_id' => $vendor->id]);
    $referrer = User::factory()->create();
    $code = ReferralCode::factory()->create(['influencer_id' => $referrer->id]);

    // Fake Paystack HTTP response
    \Illuminate\Support\Facades\Http::fake([
        'api.paystack.co/transaction/initialize' => \Illuminate\Support\Facades\Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://paystack.test/authz',
                'access_code' => 'AC_TEST_123',
                'reference' => 'VOP-FAKEREF',
            ],
        ], 200),
    ]);

    $service = $this->app->make(VendorOnboardingPaymentService::class);
    $result = $service->initializePayment($application, $code->code);

    $this->assertTrue($result['success']);
    $this->assertSame(150.0, (float) $result['payment']->amount);
    $this->assertSame(50.0, (float) $result['payment']->discount_amount);
    $this->assertSame($code->id, $result['payment']->referral_code_id);
    $this->assertSame(150.0, (float) $application->fresh()->final_amount);
    $this->assertSame($code->id, $application->fresh()->referral_code_id);
}
```

(The `paymentReady()` factory state should yield an application with `completed_step >= 4` and `payment_required = true`. Add the state to `VendorApplicationFactory` if it does not exist — inspect the existing factory and follow its conventions.)

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=initialize_payment_persists_referral_code_id_and_final_amount`

Expected: fails — current code calls `calculateFinalAmount(null)` (no discount applied) and does not write `referral_code_id` to the payment row.

- [ ] **Step 3: Update `initializePayment`**

In `app/Services/VendorOnboardingPaymentService.php`:

Change the `calculateFinalAmount` call (around line 130) from:
```php
$amounts = $application->calculateFinalAmount(null);
```
to:
```php
$amounts = $application->calculateFinalAmount($referralCodeModel);
```

In the `VendorOnboardingPayment::create([...])` array (around line 193), add `'referral_code_id' => $referralCodeModel?->id,` right after `'vendor_application_id' => $application->id,`.

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=initialize_payment_persists_referral_code_id_and_final_amount`

Expected: pass.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/VendorOnboardingPaymentService.php tests/Unit/Services/VendorOnboardingPaymentServiceTest.php database/factories/VendorApplicationFactory.php
git commit -m "feat(referral): persist referral_code_id on payment and charge subsidized amount"
```

---

## Task 6: Rewrite `ReferralService::activateReferral` to always award points

**Files:**
- Modify: `app/Services/ReferralService.php` (method at ~line 194)
- Test: `tests/Unit/Services/ReferralServiceTest.php` (update existing + add new)

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Services/ReferralServiceTest.php`:

```php
/** @test */
public function activate_referral_awards_points_based_on_final_amount_for_every_role(): void
{
    \App\Models\Setting::set('vendor_onboarding_subsidy_pct', '25', 'number');
    \App\Models\Setting::set('referral_bonus_customer_pct', '10', 'number');
    \App\Models\Setting::set('referral_bonus_vendor_pct', '10', 'number');
    \App\Models\Setting::set('referral_bonus_influencer_pct', '10', 'number');
    \App\Models\Setting::set('referral_bonus_field_agent_pct', '10', 'number');
    \App\Models\Setting::set('referral_bonus_marketer_pct', '10', 'number');
    \App\Models\Setting::set('referral_points_per_ghs', '10', 'number');

    foreach (['customer', 'vendor', 'influencer', 'field_agent', 'marketer'] as $role) {
        $referrer = \App\Models\User::factory()->create(['role' => $role, 'referral_points' => 0]);
        $code = \App\Models\ReferralCode::factory()->create(['influencer_id' => $referrer->id]);
        $application = \App\Models\VendorApplication::factory()->tier1()->approvedForPayment()->create([
            'referral_code_id' => $code->id,
            'onboarding_fee' => 200.00,
            'discount_amount' => 50.00,
            'final_amount' => 150.00,
        ]);
        $referral = \App\Models\Referral::factory()->create([
            'referral_code_id' => $code->id,
            'influencer_id' => $referrer->id,
            'vendor_id' => $application->user_id,
            'vendor_application_id' => $application->id,
            'status' => \App\Models\Referral::STATUS_PENDING,
        ]);

        $service = $this->app->make(\App\Services\ReferralService::class);
        $service->activateReferral($application);

        $referral->refresh();
        $referrer->refresh();

        $this->assertSame(\App\Models\Referral::STATUS_ACTIVE, $referral->status, "role={$role}");
        $this->assertSame('15.00', (string) $referral->earned_amount, "role={$role}");
        $this->assertSame(150, $referrer->referral_points, "role={$role}");
        $this->assertDatabaseMissing('earnings', [
            'user_id' => $referrer->id,
            'earning_type' => \App\Models\Earning::TYPE_REFERRAL_BONUS,
        ]);
    }
}

/** @test */
public function activate_referral_does_not_create_milestone_rewards(): void
{
    \App\Models\Setting::set('referral_bonus_customer_pct', '100', 'number'); // force large reward
    \App\Models\Setting::set('referral_points_per_ghs', '10', 'number');

    $referrer = \App\Models\User::factory()->create(['role' => 'customer', 'referral_points' => 0]);
    $code = \App\Models\ReferralCode::factory()->create(['influencer_id' => $referrer->id]);
    $application = \App\Models\VendorApplication::factory()->tier1()->approvedForPayment()->create([
        'referral_code_id' => $code->id,
        'onboarding_fee' => 200.00,
        'discount_amount' => 0.00,
        'final_amount' => 200.00, // 100% of 200 GHS × 10 = 2000 points, past 1000 threshold
    ]);
    \App\Models\Referral::factory()->create([
        'referral_code_id' => $code->id,
        'influencer_id' => $referrer->id,
        'vendor_id' => $application->user_id,
        'vendor_application_id' => $application->id,
        'status' => \App\Models\Referral::STATUS_PENDING,
    ]);

    $this->app->make(\App\Services\ReferralService::class)->activateReferral($application);

    $this->assertDatabaseCount('referral_milestone_rewards', 0);
}
```

(Add `approvedForPayment()` state to `VendorApplicationFactory` if missing — it should set `payment_completed = true`, `onboarding_fee`, `discount_amount`, `final_amount` to the values passed in via `create(...)`.)

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test --compact --filter=ReferralServiceTest`

Expected: both new tests fail — current code creates `Earning` rows for earning-capable roles and calls `checkMilestones`.

- [ ] **Step 3: Rewrite `activateReferral`**

In `app/Services/ReferralService.php`, replace the entire `activateReferral` method:

```php
/**
 * Activate a referral when vendor application is approved.
 *
 * All roles earn referral points — no GHS Earning rows are created. The
 * reward is a percentage of what the vendor actually paid (post-subsidy),
 * converted to points via the `referral_points_per_ghs` setting.
 *
 * @return Referral|null Null if application has no referral code.
 */
public function activateReferral(VendorApplication $vendorApplication): ?Referral
{
    if (! $vendorApplication->referral_code_id) {
        return null;
    }

    $referral = Referral::where('vendor_application_id', $vendorApplication->id)
        ->where('status', Referral::STATUS_PENDING)
        ->first();

    if (! $referral) {
        return null;
    }

    return DB::transaction(function () use ($referral, $vendorApplication) {
        $referral->activate();

        // Pessimistic lock prevents races with concurrent role changes.
        $sharer = User::lockForUpdate()->findOrFail($referral->influencer_id);

        $percentage = (float) Setting::get("referral_bonus_{$sharer->role}_pct", 0);
        $finalAmount = (float) $vendorApplication->final_amount;
        $ghsAmount = round(($percentage / 100) * $finalAmount, 2);

        $pointsPerGhs = (int) Setting::get('referral_points_per_ghs', 10);
        $points = (int) round($ghsAmount * $pointsPerGhs);

        $referral->update(['earned_amount' => $ghsAmount]);

        if ($points > 0) {
            $this->awardPoints($referral, $points);
        }

        return $referral->fresh();
    });
}
```

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `php artisan test --compact --filter=ReferralServiceTest`

Expected: both new tests pass. Existing tests in the file may still fail — fixed in Task 9.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ReferralService.php tests/Unit/Services/ReferralServiceTest.php database/factories/VendorApplicationFactory.php
git commit -m "feat(referral): unify activation to single points lane for all roles"
```

---

## Task 7: Simplify `awardPoints` — remove `checkMilestones` call

**Files:**
- Modify: `app/Services/ReferralService.php` (method at ~line 287)
- Test: append to `tests/Unit/Services/ReferralServiceTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Services/ReferralServiceTest.php`:

```php
/** @test */
public function award_points_does_not_create_milestone_rewards_even_past_threshold(): void
{
    $referrer = \App\Models\User::factory()->create(['role' => 'customer', 'referral_points' => 900]);
    $code = \App\Models\ReferralCode::factory()->create(['influencer_id' => $referrer->id]);
    $application = \App\Models\VendorApplication::factory()->tier1()->approvedForPayment()->create();
    $referral = \App\Models\Referral::factory()->create([
        'referral_code_id' => $code->id,
        'influencer_id' => $referrer->id,
        'vendor_id' => $application->user_id,
        'vendor_application_id' => $application->id,
        'status' => \App\Models\Referral::STATUS_ACTIVE,
    ]);

    // Cross the 1000 threshold directly.
    $this->app->make(\App\Services\ReferralService::class)->awardPoints($referral, 200);

    $this->assertSame(1100, $referrer->fresh()->referral_points);
    $this->assertDatabaseCount('referral_milestone_rewards', 0);
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=award_points_does_not_create_milestone_rewards_even_past_threshold`

Expected: fails — `awardPoints` still calls `checkMilestones` which creates a reward row.

- [ ] **Step 3: Remove the `checkMilestones` call**

In `app/Services/ReferralService.php`, in `awardPoints`, delete this line:

```php
$this->checkMilestones($user, $oldPoints, $newPoints);
```

The `$oldPoints` and `$newPoints` locals become unused — delete the lines that compute them so the method body reads:

```php
return DB::transaction(function () use ($referral, $points, $reason, $description) {
    $user = User::lockForUpdate()->findOrFail($referral->influencer_id);

    $user->increment('referral_points', $points);

    return ReferralPointTransaction::create([
        'user_id' => $user->id,
        'referral_id' => $referral->id,
        'points' => $points,
        'reason' => $reason,
        'description' => $description
            ?? "Points for vendor onboarding: {$referral->vendor->name}",
    ]);
});
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=award_points_does_not_create_milestone_rewards_even_past_threshold`

Expected: pass.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ReferralService.php tests/Unit/Services/ReferralServiceTest.php
git commit -m "refactor(referral): stop creating milestone rewards in awardPoints"
```

---

## Task 8: Add defence-in-depth self-referral guard in `applyReferralCode`

**Files:**
- Modify: `app/Services/ReferralService.php` (method at ~line 147)
- Test: append to `tests/Unit/Services/ReferralServiceTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Services/ReferralServiceTest.php`:

```php
/** @test */
public function apply_referral_code_rejects_self_referral(): void
{
    $user = \App\Models\User::factory()->create();
    $code = \App\Models\ReferralCode::factory()->create(['influencer_id' => $user->id]);
    $application = \App\Models\VendorApplication::factory()->create(['user_id' => $user->id]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('You cannot use your own referral code.');

    $this->app->make(\App\Services\ReferralService::class)
        ->applyReferralCode($application, $code->code);
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --compact --filter=apply_referral_code_rejects_self_referral`

Expected: fails — no guard in `applyReferralCode` itself (only in the upstream service).

- [ ] **Step 3: Add the guard**

In `app/Services/ReferralService.php`, inside `applyReferralCode`, after the code lookup and before the "already has a code" check, add:

```php
if ((int) $referralCode->influencer_id === (int) $vendorApplication->user_id) {
    throw new \RuntimeException('You cannot use your own referral code.');
}
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php artisan test --compact --filter=apply_referral_code_rejects_self_referral`

Expected: pass.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ReferralService.php tests/Unit/Services/ReferralServiceTest.php
git commit -m "feat(referral): guard against self-referral in ReferralService::applyReferralCode"
```

---

## Task 9: Delete dead code and obsolete config

**Files:**
- Modify: `app/Services/ReferralService.php`
- Modify: `config/referral.php`
- Modify: `tests/Unit/Services/ReferralServiceTest.php`, `tests/Feature/DynamicRegistrationBonusTest.php`
- Modify: `app/Models/VendorOnboardingPayment.php` (remove `coupon_id` from `$fillable` — optional cleanup; see note)

- [ ] **Step 1: Update tests that reference dead methods/keys**

In `tests/Unit/Services/ReferralServiceTest.php`, find and remove any test that calls or asserts on:
- `calculateRegistrationBonus()`
- `checkMilestones()`
- `getMilestoneThresholds()`

Run: `php artisan test --compact --filter=ReferralServiceTest` and note which tests fail or error. Update them to assert the new points-lane behavior (same shape as the tests added in Task 6), or delete them if fully obsolete.

In `tests/Feature/DynamicRegistrationBonusTest.php`, update any test that expects `calculateRegistrationBonus` to still exist. Base calculations should now run through `activateReferral` with a known `vendor_applications.final_amount`.

- [ ] **Step 2: Delete the dead service methods**

In `app/Services/ReferralService.php`, delete these methods entirely:
- `calculateRegistrationBonus(string $referrerRole, int $vendorTier): float`
- `checkMilestones(User $user, int $oldPoints, int $newPoints): void`
- `getMilestoneThresholds(int $uptoPoints): array`

Also remove the now-unused `use App\Models\ReferralMilestoneReward;` import at the top of the file (verify grep shows no other usage inside this file before removing).

Also update the class docblock (lines 15–29) — the current text describes a two-lane flow and commission periods that no longer exist. Replace with:

```php
/**
 * ReferralService — manages the platform's referral program.
 *
 * Referral Lifecycle:
 * 1. Any user creates a referral code to share.
 * 2. A vendor uses the code during onboarding; a Referral row is created
 *    in 'pending' status and the vendor receives a subsidy on the
 *    onboarding fee.
 * 3. When the vendor application is approved, the referral activates and
 *    the referrer is awarded points based on a percentage of what the
 *    vendor actually paid. All roles earn points — no GHS Earning rows
 *    are created.
 */
```

- [ ] **Step 3: Delete obsolete config keys**

Replace the contents of `config/referral.php` with an empty return (the three deleted keys leave nothing meaningful behind):

```php
<?php

return [
    // Intentionally empty — previous keys (milestone_first, milestone_increment,
    // points_per_vendor_onboarding) moved to the settings table in the
    // 2026-04 referral redesign. See docs/superpowers/specs/2026-04-18-referral-program-redesign-design.md.
];
```

(If `config/referral.php` is not loaded anywhere, you can delete the file entirely. Grep first: `grep -rn "config('referral" app config`.)

- [ ] **Step 4: Run the full referral test suites**

Run: `php artisan test --compact --filter=Referral`

Expected: all green. If anything still references a deleted symbol, fix it and re-run.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ReferralService.php config/referral.php tests/
git commit -m "refactor(referral): remove legacy commission/milestone code paths"
```

---

## Task 10: Settings controller validation for the 3 new keys

**Files:**
- Modify: `app/Http/Controllers/Settings/VendorOnboardingController.php`
- Modify: `tests/Feature/Settings/VendorOnboardingSettingsTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Settings/VendorOnboardingSettingsTest.php`:

```php
/** @test */
public function admin_can_update_subsidy_and_points_settings(): void
{
    $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

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
            'referral_bonus_marketer_pct' => '20',
            'vendor_onboarding_subsidy_pct' => '30',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
        ])
        ->assertSessionHas('success');

    $this->assertSame('30', \App\Models\Setting::get('vendor_onboarding_subsidy_pct'));
    $this->assertSame('10', \App\Models\Setting::get('referral_points_per_ghs'));
    $this->assertSame('1000', \App\Models\Setting::get('referral_cashout_min_points'));
}

/** @test */
public function subsidy_pct_must_be_between_0_and_100(): void
{
    $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

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
            'referral_bonus_marketer_pct' => '20',
            'vendor_onboarding_subsidy_pct' => '150',
            'referral_points_per_ghs' => '10',
            'referral_cashout_min_points' => '1000',
        ])
        ->assertSessionHasErrors('vendor_onboarding_subsidy_pct');
}
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test --compact --filter=VendorOnboardingSettingsTest`

Expected: both fail — validation rules don't include the 3 new keys.

- [ ] **Step 3: Add validation rules**

In `app/Http/Controllers/Settings/VendorOnboardingController.php`, update the `$validated` array in `update()`:

```php
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
    'vendor_onboarding_subsidy_pct' => 'required|numeric|min:0|max:100',
    'referral_points_per_ghs' => 'required|integer|min:1',
    'referral_cashout_min_points' => 'required|integer|min:1',
]);
```

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `php artisan test --compact --filter=VendorOnboardingSettingsTest`

Expected: pass.

- [ ] **Step 5: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Settings/VendorOnboardingController.php tests/Feature/Settings/VendorOnboardingSettingsTest.php
git commit -m "feat(referral-settings): validate subsidy and points settings on update"
```

---

## Task 11: Settings UI — new cards + helper text

**Files:**
- Modify: `resources/js/pages/settings/vendor-onboarding.tsx`

- [ ] **Step 1: Extend the `Settings` interface**

In `resources/js/pages/settings/vendor-onboarding.tsx`, add to the `Settings` interface:

```tsx
vendor_onboarding_subsidy_pct?: { value: string; type: string; description: string };
referral_points_per_ghs?: { value: string; type: string; description: string };
referral_cashout_min_points?: { value: string; type: string; description: string };
```

- [ ] **Step 2: Update the helper description on the Referral Bonus card**

Change the `<CardDescription>` under `<CardTitle>Referral Bonus Percentages</CardTitle>`:

```tsx
<CardDescription>
    The share of the amount a vendor actually paid (after the subsidy) that each role earns as points when their referral code is used.
    Example: if Customer = 15% and the vendor paid GH₵150, a Customer referrer earns GH₵22.50, shown as 225 points at the current conversion rate.
</CardDescription>
```

Also update the `<Typography>` preview inside `ReferralBonusFields` — the "Tier 1: GH₵X | Tier 2: GH₵Y" line — to show post-subsidy amounts. Read `subsidyPct` from settings:

```tsx
const subsidyPct = parseFloat(settings.vendor_onboarding_subsidy_pct?.value || '25');
const postSubsidy = (fee: number) => fee * (1 - subsidyPct / 100);
```

And render:

```tsx
<Typography variant="body2" color="text.secondary">
    Tier 1 post-subsidy: GH₵{postSubsidy(tier1Fee).toFixed(2)} → {cat.label} earns GH₵{computeBonus(percentages[cat.key], postSubsidy(tier1Fee))} |
    Tier 2 post-subsidy: GH₵{postSubsidy(tier2Fee).toFixed(2)} → earns GH₵{computeBonus(percentages[cat.key], postSubsidy(tier2Fee))}
</Typography>
```

- [ ] **Step 3: Add the Vendor Subsidy card**

After the existing Tier 2 card (the closing `</Card>` near line 313) and before the Referral Bonus Percentages card, insert:

```tsx
{/* Vendor Subsidy Card */}
<Card>
    <CardHeader>
        <CardTitle>Vendor Subsidy</CardTitle>
        <CardDescription>
            The discount applied to a vendor's onboarding fee when they onboard using a valid referral code.
            Applies identically to Tier 1 and Tier 2. Example: at 25%, a Tier 1 vendor with a 200 GHS fee pays 150 GHS.
        </CardDescription>
    </CardHeader>
    <CardContent>
        <Box sx={{ display: 'grid', gap: 1 }}>
            <Label htmlFor="vendor_onboarding_subsidy_pct">Subsidy (%)</Label>
            <Input
                id="vendor_onboarding_subsidy_pct"
                name="vendor_onboarding_subsidy_pct"
                type="number"
                step="0.01"
                min="0"
                max="100"
                defaultValue={settings.vendor_onboarding_subsidy_pct?.value || '25.00'}
                required
            />
            <InputError message={errors.vendor_onboarding_subsidy_pct} />
        </Box>
    </CardContent>
</Card>
```

- [ ] **Step 4: Add the Referral Points System card**

Insert after the Referral Bonus Percentages card (before the Save button `<Box>`):

```tsx
{/* Referral Points System Card */}
<Card>
    <CardHeader>
        <CardTitle>Referral Points System</CardTitle>
        <CardDescription>
            Controls how referrer rewards display and when referrers can cash out.
            A referrer earning GH₵15 sees 150 points at a 10-per-GHS rate, and can cash out once they reach 1000 points (GH₵100).
        </CardDescription>
    </CardHeader>
    <CardContent>
        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
            <Box sx={{ display: 'grid', gap: 1 }}>
                <Label htmlFor="referral_points_per_ghs">Points per GHS</Label>
                <Input
                    id="referral_points_per_ghs"
                    name="referral_points_per_ghs"
                    type="number"
                    step="1"
                    min="1"
                    defaultValue={settings.referral_points_per_ghs?.value || '10'}
                    required
                />
                <Typography variant="body2" color="text.secondary">
                    How many points a referrer sees per 1 GHS earned.
                </Typography>
                <InputError message={errors.referral_points_per_ghs} />
            </Box>
            <Box sx={{ display: 'grid', gap: 1 }}>
                <Label htmlFor="referral_cashout_min_points">Minimum points to cash out</Label>
                <Input
                    id="referral_cashout_min_points"
                    name="referral_cashout_min_points"
                    type="number"
                    step="1"
                    min="1"
                    defaultValue={settings.referral_cashout_min_points?.value || '1000'}
                    required
                />
                <Typography variant="body2" color="text.secondary">
                    The lowest balance at which a referrer can request a withdrawal.
                </Typography>
                <InputError message={errors.referral_cashout_min_points} />
            </Box>
        </Box>
    </CardContent>
</Card>
```

- [ ] **Step 5: Rebuild the frontend and smoke-test**

Run: `pnpm run build`

Then visit `/settings/vendor-onboarding` in the browser. Confirm:
- The Vendor Subsidy card shows with value 25.
- The Referral Points System card shows with values 10 and 1000.
- The Referral Bonus Percentages card shows updated description and per-row preview using post-subsidy amounts.
- Changing the subsidy input and saving updates the value in the database.

Use `mcp__laravel-boost__browser-logs` if anything errors in the browser console.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/settings/vendor-onboarding.tsx
git commit -m "feat(referral-settings): add Vendor Subsidy and Referral Points System cards"
```

---

## Task 12: Update existing tests that now fail

**Files:**
- Modify: `tests/Feature/DynamicRegistrationBonusTest.php`
- Modify: `tests/Feature/Http/ReferralMilestoneRewardControllerTest.php`
- Modify: `tests/Unit/UserReferralPayoutTest.php` (review)
- Modify: `tests/Feature/ReferralBonusSettingsTest.php` (review)

- [ ] **Step 1: Run the full referral-related suite and collect failures**

Run: `php artisan test --compact --filter=Referral`

Note every failing test and the assertion that fails.

- [ ] **Step 2: Update `DynamicRegistrationBonusTest`**

The premise of this test was "registration bonus = role_pct × tier_fee". The new premise is "referrer points = role_pct × final_amount × points_per_ghs". Rewrite assertions accordingly. Create the test's `VendorApplication` with an explicit `final_amount` (e.g., 150.00) so the math is deterministic.

If a test asserts creation of an `Earning` row, change it to assert:
- `referrals.earned_amount` has the expected GHS value.
- `users.referral_points` is incremented by the expected number.
- No new `earnings` row is created.

- [ ] **Step 3: Update `ReferralMilestoneRewardControllerTest`**

For any test that asserts automatic creation of a `ReferralMilestoneReward` by the referral activation flow, flip the assertion:

```php
$this->assertDatabaseCount('referral_milestone_rewards', 0);
```

Admin operations on existing rows (list, fulfill, etc.) should still work — keep those tests intact. They cover the read-only-legacy view that remains in place until Phase 2.

- [ ] **Step 4: Review `UserReferralPayoutTest` and `ReferralBonusSettingsTest`**

Run each in isolation. Fix anything that assumed the earning-capable branch; don't touch tests that still make sense.

- [ ] **Step 5: Run the full referral suite**

Run: `php artisan test --compact --filter=Referral`

Expected: all green.

- [ ] **Step 6: Pint format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/
git commit -m "test(referral): realign existing tests with unified points lane"
```

---

## Task 13: Full suite, Pint, and final smoke

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`

Expected: all green. Fix any regression — Task 9's deletions or Task 6's rewrite may have touched a peripheral test. Diagnose root cause; don't silence tests.

- [ ] **Step 2: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty --format agent`

Expected: no style issues left.

- [ ] **Step 3: Manual staging smoke test**

On staging (or via `mcp__laravel-boost__tinker` against a seeded DB):

1. Super admin bumps subsidy 25 → 30 on `/settings/vendor-onboarding`; live preview on the Referral Bonus card reflects the new subsidy.
2. A vendor (fresh user) creates a vendor application, applies a valid referral code from a customer user.
3. `validateReferralCode` API returns `final_amount = fee × 0.70`.
4. Vendor completes Paystack payment at the subsidized amount.
5. Admin approves the vendor application.
6. `users.referral_points` on the customer increments by `final_amount × customer_pct / 100 × 10`.
7. `earnings` table has no new row with `earning_type = 'referral_bonus'`.
8. `referral_milestone_rewards` has no new row.

Capture the diff against the spec's worked example (200 fee, 25% subsidy, 15% customer = 225 points). Flag any divergence to the reviewer.

- [ ] **Step 4: Operational pre-cutover step (documented, not code)**

Before merging to main: fulfil all currently pending `ReferralMilestoneReward` rows on production via the existing admin page. The spec assumes no pending rows at cutover.

- [ ] **Step 5: Open the PR**

```bash
gh pr create --title "feat(referral): Phase 1 redesign — subsidy + unified points lane" --body "$(cat <<'EOF'
## Summary
- Adds 3 new settings: vendor subsidy %, points-per-GHS, cashout minimum points.
- Unifies every role to a single referral reward lane (points only) based on what the vendor actually paid.
- Removes legacy commission/milestone branching in `ReferralService`.
- Persists `referral_code_id` on vendor onboarding payments for audit.

Full spec: `docs/superpowers/specs/2026-04-18-referral-program-redesign-design.md`.

## Test plan
- [ ] `php artisan test --compact` green
- [ ] Staging smoke per Task 13 step 3
- [ ] Pending milestone rewards on prod fulfilled before merge

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Spec coverage checklist

- [x] Task 1 — 3 new settings (subsidy, points-per-GHS, cashout min) → spec §5
- [x] Task 2 — `referral_code_id` on `vendor_onboarding_payments` → spec §8
- [x] Task 3 — subsidy in `calculateFinalAmount` → spec §6 / §7
- [x] Task 4 — `validateReferralCode` returns subsidy-adjusted amounts → spec §6
- [x] Task 5 — `initializePayment` persists `referral_code_id` + charges subsidized amount → spec §6
- [x] Task 6 — `activateReferral` unified points lane → spec §7
- [x] Task 7 — `awardPoints` no more milestone check → spec §7
- [x] Task 8 — self-referral guard in `applyReferralCode` → spec §6
- [x] Task 9 — dead code + config key removal → spec §7 / §8
- [x] Task 10 — settings validation for 3 new keys → spec §5
- [x] Task 11 — settings UI cards + helper text + post-subsidy previews → spec §5
- [x] Task 12 — update existing tests → spec §9
- [x] Task 13 — full suite + operational pre-cutover + PR → spec §8

## Intentionally deferred (not in this plan)

- **Phase 0**: `marketer` → `employee` rename — separate spec.
- **Phase 2**: user-facing points-wallet cashout UI and enforcement of `referral_cashout_min_points`, retiring the milestone-rewards admin page — separate spec.
- **Phase 3**: "Have a coupon code?" → "Have a referral code?" label on vendor-onboarding UI — small follow-up.
- Dropping legacy columns (`coupon_id` on `vendor_applications` and `vendor_onboarding_payments`, `registration_bonus` on `referral_codes`) — cleanup migration after Phase 2 ships.
