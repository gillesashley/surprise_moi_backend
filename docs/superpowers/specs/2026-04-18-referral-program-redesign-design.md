# Referral Program Redesign — Design Spec

**Date:** 2026-04-18
**Status:** Draft — pending review
**Scope:** Phase 1 of a phased rollout. Phase 0 (role rename) and Phases 2–3 (cashout flow, UI copy) are tracked as separate specs.

---

## 1. Context

The current referral program has two parallel reward lanes:

- **Earning-capable roles** (`influencer`, `field_agent`, `marketer`) — receive GHS via `Earning` rows.
- **Everyone else** — receive points via `ReferralPointTransaction`, with discrete `ReferralMilestoneReward` rows auto-created at thresholds (1000, 5000, 10000…) for admin fulfilment.

A half-finished migration has also left vendor onboarding with two discount mechanisms: a legacy `Coupon`-based flow in `VendorOnboardingPaymentService`, and a parallel `ReferralCode` flow via `ReferralService::applyReferralCode`. Coupons are meant for cart/product purchases only, not vendor onboarding.

The redesign unifies the reward model and completes the coupon-to-referral consolidation at onboarding.

## 2. Goals

1. Every role earns referrals the same way: **points**, based on a percentage of what the vendor actually paid.
2. A **vendor subsidy** (single %, applied to both tiers) reduces the vendor's onboarding fee when a valid referral code is used.
3. **Coupons are off-limits in vendor onboarding.** They remain alive for cart/product purchases.
4. Preserve historical audit — no data deletion in Phase 1.

## 3. Non-Goals (Phase 1)

- The user-facing "points wallet" cashout UI and payout flow — **Phase 2**.
- Retiring the milestone-rewards admin page — **Phase 2**.
- Renaming `marketer` → `employee` codebase-wide — **Phase 0**, separate pre-req.
- Fixing the "Have a coupon code?" label on the vendor-onboarding UI — **Phase 3**, separate small follow-up.

## 4. The new reward model

### Conceptual flow

1. Referrer (any role) already has a referral code.
2. Vendor onboards, enters the referral code.
3. Subsidy % is deducted from the onboarding fee; vendor pays the reduced amount.
4. Admin approves the vendor application.
5. Referrer's reward = `(role_pct / 100) × vendor_paid_amount`.
6. Reward is converted to points at `points_per_ghs` and added to `users.referral_points`.
7. Referrer displays balance as points (e.g., 22.50 GHS → 225 points).

### Worked example

- Vendor onboarding fee: 200 GHS (Tier 1).
- Subsidy: 25% → vendor pays 150 GHS.
- Referrer is a customer; `referral_bonus_customer_pct` = 15%.
- Referrer's GHS reward: 150 × 0.15 = **22.50 GHS**.
- Referrer's points: 22.50 × 10 = **225 points**.

### Snapshot principle

Three values are snapshotted at **payment time** and never recomputed:
- `vendor_applications.onboarding_fee` — tier fee at time of payment.
- `vendor_applications.discount_amount` — subsidy amount at time of payment.
- `vendor_applications.final_amount` — what vendor actually paid.

One value is read live at **activation time**:
- `referral_bonus_{role}_pct` — fresh from settings. Reflects platform's current commitment to referrers.

## 5. Settings page changes (`/settings/vendor-onboarding`)

### New settings rows (seeded via migration)

| Key | Default | Type | Purpose |
|---|---|---|---|
| `vendor_onboarding_subsidy_pct` | `25.00` | number | Discount on vendor onboarding fee when a valid referral code is used. Applies to both tiers. |
| `referral_points_per_ghs` | `10` | number | Conversion rate. 1 GHS earned → 10 points displayed. |
| `referral_cashout_min_points` | `1000` | number | Minimum points a user must have before requesting a cashout. (Enforced in Phase 2; persisted now so admin can set value early.) |

### New cards on the settings page

Added below the existing Tier 1 / Tier 2 / Referral Bonus cards:

1. **"Vendor Subsidy" card** — one field: `Subsidy %`. Live preview: _"Tier 1 vendor pays GH₵X after Y% subsidy. Tier 2 vendor pays GH₵Z."_
2. **"Referral Points System" card** — two fields: `Points per GHS`, `Minimum points to cash out`. Preview: _"A referrer earning GH₵15 sees 150 points. They can cash out once they reach 1000 points (GH₵100)."_

### Existing "Referral Bonus Percentages" card

- 5 rows stay (`customer`, `vendor`, `influencer`, `field_agent`, `marketer`).
  - `marketer` row's key/label becomes `employee` after Phase 0 lands — this spec does not drive that rename.
- Helper text updates: _"Percentage of the amount the vendor actually paid (after subsidy) that the referrer earns as points."_
- Live preview per row recomputes against post-subsidy amounts, not full tier fee.

### Form request validation

The existing vendor-onboarding form request gains three new rules:

- `vendor_onboarding_subsidy_pct` — numeric, 0–100.
- `referral_points_per_ghs` — integer, ≥1.
- `referral_cashout_min_points` — integer, ≥1.

## 6. Vendor onboarding payment flow

### Current messy state

- `VendorOnboardingPaymentService::validateCoupon` reads from `Coupon`, computes discount from `coupon.percentage`, writes `coupon_id` to the application.
- `ReferralService::applyReferralCode` exists in parallel.

### New consolidated flow

1. Vendor enters a referral code (the UI currently labels this "Have a coupon code?"; that label is fixed in Phase 3).
2. `VendorOnboardingPaymentService::validateReferralCode($code, $application)`:
   - Looks up via `ReferralCode::where('code', $code)->valid()->first()`.
   - Rejects if invalid / expired / maxed out.
   - Rejects if `referralCode->influencer_id === $application->user_id` (self-referral guard).
   - Computes `subsidy_amount = onboarding_fee × subsidy_pct / 100`.
   - Returns `onboarding_fee`, `subsidy_amount`, `final_amount`.
3. `initializePayment($application, ?string $referralCode, ?string $callbackUrl)`:
   - Signature changes: `$couponCode` → `$referralCode`.
   - On valid code: call `ReferralService::applyReferralCode` (existing — creates the pending `Referral` and attaches `referral_code_id` to the application).
   - Writes `final_amount` and `discount_amount` to `vendor_applications` and `vendor_onboarding_payments` (today's behaviour — just with the new discount source).
   - Metadata keys: `referral_code`, `referral_code_id` (replacing `coupon_code`, `coupon_id`).
4. Paystack flow — unchanged.
5. Verification — unchanged, except the "update coupon usage" block (`VendorOnboardingPaymentService.php:401-409`) is deleted. `ReferralCode::incrementUsage` is already called inside `applyReferralCode`.

### Self-referral guard

Implemented in two places:
- `VendorOnboardingPaymentService::validateReferralCode` — user-facing error.
- `ReferralService::applyReferralCode` — defence in depth.

Message: _"You cannot use your own referral code."_

## 7. Referral activation logic

### `ReferralService::activateReferral(VendorApplication $application)`

Rewritten flow:

```
1. Return null if $application->referral_code_id is null.
2. Find pending Referral for this application. Return null if none.
3. DB::transaction:
   a. $referral->activate()
   b. Lock sharer user row (prevents role-change races).
   c. $pct  = Setting::get("referral_bonus_{$sharer->role}_pct", 0)
   d. $ghs  = round(($pct / 100) * $application->final_amount, 2)
   e. $ppg  = (int) Setting::get('referral_points_per_ghs', 10)
   f. $points = (int) round($ghs * $ppg)
   g. $referral->update(['earned_amount' => $ghs])   // audit
   h. $this->awardPoints($referral, $points)
4. Return fresh referral.
```

No `Earning` rows are created. No role branching.

### `ReferralService::awardPoints()` simplification

- Still increments `users.referral_points` under a row lock.
- Still writes a `ReferralPointTransaction`.
- **Removes the `checkMilestones` call** — milestone machinery is frozen.

### Deleted methods and config

- `ReferralService::calculateRegistrationBonus()` — dead.
- `ReferralService::checkMilestones()` — dead.
- `ReferralService::getMilestoneThresholds()` — dead.
- `config/referral.php` keys `milestone_first`, `milestone_increment`, `points_per_vendor_onboarding` — deleted.

## 8. Data model & migrations

### Migrations (in order)

1. **Seed 3 new settings** — values as above.
2. **Add `vendor_onboarding_payments.referral_code_id`** — nullable FK → `referral_codes.id`. Mirrors what `vendor_applications` already has.

No drops. No renames. Nothing else.

### Freeze policy (no new writes, existing data preserved)

| Table / column | Status |
|---|---|
| `vendor_applications.coupon_id` | Frozen. Existing rows untouched. |
| `vendor_onboarding_payments.coupon_id` | Frozen. |
| `referral_codes.registration_bonus` | Frozen — never read by new code. |
| `referral_milestone_rewards` (all rows) | Frozen — no new inserts. Existing rows remain visible on the admin page until Phase 2. |
| `earnings` rows with `earning_type='referral_bonus'` | Frozen — no new inserts. Existing rows still show in user earnings history. |

### Newly populated

- `referrals.earned_amount` — was vestigial; now stores the GHS value of the referrer's reward for audit.

### Pre-cutover operational step

Before Phase 1 ships, **fulfil all pending `ReferralMilestoneReward` rows** via the existing admin page (confirmed with user — the set is small, and there are no real-world coupon rows to worry about).

## 9. Testing

### Existing tests to update

| Test file | What changes |
|---|---|
| `tests/Unit/Services/ReferralServiceTest.php` | `activateReferral` no longer creates `Earning` rows for earning-capable roles. Assertions flip: all roles award points, `earnings` table untouched, `referral_milestone_rewards` untouched. |
| `tests/Feature/DynamicRegistrationBonusTest.php` | Calculation base is `final_amount` (post-subsidy), not the tier fee. Update expected values. |
| `tests/Feature/ReferralBonusSettingsTest.php` | Keep as-is; per-role percentages still persist via the same keys. |
| `tests/Feature/Settings/VendorOnboardingSettingsTest.php` | Add assertions for the 3 new settings fields and their validation. |
| `tests/Feature/Http/ReferralMilestoneRewardControllerTest.php` | Assert that new referrals do **not** create milestone rows. Existing rows still readable. |
| `tests/Unit/UserReferralPayoutTest.php` | Review; update if it relied on the earning-capable branch. |

### New tests

| Test | Purpose |
|---|---|
| `VendorOnboardingPaymentServiceTest::validate_referral_code_rejects_self_referral` | Code owner cannot use their own code. |
| `VendorOnboardingPaymentServiceTest::validate_referral_code_computes_subsidy_from_settings` | Subsidy applies to both tiers identically. |
| `VendorOnboardingPaymentServiceTest::initialize_payment_attaches_referral_code_not_coupon` | Writes `referral_code_id`; no `coupon_id` writes. |
| `ReferralServiceTest::activate_referral_awards_points_based_on_final_amount` | Base is `vendor_application.final_amount`, not the tier fee. |
| `ReferralServiceTest::activate_referral_populates_earned_amount` | GHS value lands on `referrals.earned_amount`. |
| `ReferralServiceTest::activate_referral_skips_milestone_creation` | No new `ReferralMilestoneReward` rows even past 1000 points. |
| `ReferralServiceTest::activate_referral_works_for_every_role` | Parametric: customer, vendor, influencer, field_agent, employee all award points; none create earnings. |

### Tests untouched

- `tests/Feature/BulkGenerateReferralCodeTest.php`
- `tests/Feature/ReferralCodePrefixTest.php`
- `tests/Feature/Api/V1/MyReferralControllerTest.php` (verify no surprises).

### Manual staging smoke test

1. Super admin bumps subsidy 25 → 30 and sees live preview update.
2. Vendor onboards with a valid referral code; pays 70% of the tier fee.
3. Admin approves the application.
4. Referrer's `users.referral_points` increments by the expected number.
5. No new `earnings` or `referral_milestone_rewards` rows are created.

## 10. Open questions / deferred decisions

1. **Role percentage snapshotting** — decided: read live from settings at activation time. A referrer sees the *current* rate, not the rate at payment time. If this changes, add a `referrer_role_pct_snapshot` column on `referrals`.
2. **Cashout enforcement** — `referral_cashout_min_points` is persisted in Phase 1 but enforced in Phase 2 when the points-wallet payout UI lands.
3. **Dropping legacy columns** (`coupon_id`, `registration_bonus`) — not part of Phase 1. Revisit in a cleanup migration after Phase 2 is stable.

## 11. Phase boundary summary

- **Phase 0 (pre-req, separate spec):** `marketer` → `employee` rename codebase-wide.
- **Phase 1 (this spec):** Subsidy + unified points lane + coupon/referral consolidation at onboarding.
- **Phase 2 (next spec):** Points-wallet cashout flow, retire milestone admin page.
- **Phase 3 (small follow-up):** Vendor-onboarding UI copy "coupon" → "referral".
