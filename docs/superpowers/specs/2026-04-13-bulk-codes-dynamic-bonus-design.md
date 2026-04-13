# Bulk Referral Code Generation & Dynamic Registration Bonus

**Date:** 2026-04-13
**Status:** Draft
**Branch:** feat/me-referral-summary-endpoint (to be branched from)

## Summary

Two enhancements to the referral system:

1. **Bulk code generation** — Admins can generate referral codes for all users in a category at once, with role-based prefixes for easy identification.
2. **Dynamic registration bonus** — Registration bonuses are calculated as a percentage of the referred person's tier onboarding fee, with percentages configurable per user category in settings.

---

## Feature 1: Bulk Referral Code Generation

### User Flow

1. Admin navigates to Referral Codes index page (`/referral-codes`)
2. Clicks "Bulk Generate" button (next to existing "Create" button)
3. Modal appears with a category dropdown (Customer, Vendor, Influencer, Field Agent, Marketer)
4. After selecting a category, a preview shows:
   - Total users in that category
   - Users who already have an active code (skipped)
   - Number of codes to generate
   - Code format preview (e.g., `VD-XXXXXXXX`)
5. Admin clicks "Generate N Codes" to confirm
6. Success state shows count of generated codes

### Code Prefix System

Each referral code is prefixed with a 2-letter identifier based on the owner's role:

| Role         | Prefix |
|--------------|--------|
| customer     | `CU-`  |
| vendor       | `VD-`  |
| influencer   | `IN-`  |
| field_agent  | `FA-`  |
| marketer     | `MK-`  |

**Format:** `{PREFIX}{8 random uppercase alphanumeric chars}` (e.g., `VD-QM7TNPIU`)

**Where prefixes apply:**
- Bulk generation — prefix derived from selected category
- Single code creation (admin form) — prefix derived from the selected user's role
- Auto-creation via MyReferralController (mobile) — prefix derived from authenticated user's role
- API creation via ReferralCodeController (influencer) — prefix derived from authenticated user's role

**Where prefixes do NOT apply:**
- Existing codes in the database — left as-is, no migration

### Bulk Generation Rules

- Only creates codes for users who do NOT already have an active referral code
- One code per user
- Codes are created within a single database transaction
- Codes are created with `is_active = true`, no expiry, no max usages
- The `registration_bonus` column is left as 0/null (bonus is calculated dynamically)

### Backend

**New controller actions on `ReferralCodeController` (web):**
- `bulkGeneratePreview(Request $request)` — accepts `role`, returns counts (total users, users with codes, users without codes)
- `bulkGenerate(BulkGenerateReferralCodeRequest $request)` — accepts `role`, creates codes, returns count

**New request:** `BulkGenerateReferralCodeRequest`
- Validates `role` is one of: `customer`, `vendor`, `influencer`, `field_agent`, `marketer`
- Authorization: admin or super_admin only

**New service method:** `ReferralService::bulkGenerateCodes(string $role): int`
- Queries users by role who have no active referral code
- Creates codes in a single transaction with appropriate prefix
- Returns count of codes created

**Model changes:** `ReferralCode`
- Add `ROLE_PREFIXES` constant mapping roles to prefixes
- Update `generateUniqueCode()` to accept an optional prefix parameter
- Add `getPrefixForRole(string $role): string` static helper

**New routes:**
- `POST /referral-codes/bulk-generate/preview` → `ReferralCodeController@bulkGeneratePreview`
- `POST /referral-codes/bulk-generate` → `ReferralCodeController@bulkGenerate`

### Frontend

**Referral codes index page:**
- Add "Bulk Generate" button in page header
- Modal component with:
  - Category dropdown
  - Preview stats panel (fetched via POST to preview endpoint)
  - Confirm button showing exact count
  - Success state
  - Loading/disabled states during generation

---

## Feature 2: Dynamic Registration Bonus

### Concept

Registration bonuses transition from a fixed GHS amount per code to a dynamic percentage-based calculation:

```
bonus = referrer's category percentage × referred person's tier onboarding fee
```

**Example:** A Vendor (20% rate) refers a Tier 1 person (GHS 150 fee) → bonus = GHS 30.

### New Settings

Five new entries in the `settings` table:

| Key                              | Type   | Default | Description                     |
|----------------------------------|--------|---------|---------------------------------|
| `referral_bonus_customer_pct`    | number | 15.00   | Customer referral bonus %       |
| `referral_bonus_vendor_pct`      | number | 20.00   | Vendor referral bonus %         |
| `referral_bonus_influencer_pct`  | number | 25.00   | Influencer referral bonus %     |
| `referral_bonus_field_agent_pct` | number | 30.00   | Field Agent referral bonus %    |
| `referral_bonus_marketer_pct`    | number | 20.00   | Marketer referral bonus %       |

### Settings UI

Added as a new "Referral Bonus Percentages" section on the existing Vendor Onboarding settings page (`/settings/vendor-onboarding`).

Each category gets a card with:
- Percentage input (numeric, 0-100, step 0.01)
- Live-calculated helper text: "Tier 1 → GHS X | Tier 2 → GHS Y"
- Helper text updates dynamically as the admin types

Saved via the existing `VendorOnboardingController@update` endpoint (extended to accept the 5 new fields).

### Bonus Calculation Logic

**In `ReferralService::activateReferral()`:**

1. Get the referrer (influencer) user's role
2. Look up `referral_bonus_{role}_pct` from settings
3. Get the referred person's vendor tier from their `VendorApplication::getVendorTier()`
4. Look up `vendor_tier{N}_onboarding_fee` from settings
5. Calculate: `percentage / 100 × onboarding_fee`
6. Use this calculated amount as the bonus

**Interaction with existing earning vs points branching:**

The current `activateReferral()` has two paths based on the referrer's role:
- **Earning-capable roles** (influencer, field_agent, marketer): receive GHS via an Earning record
- **Points-capable roles** (customer, vendor): receive points via `points_per_vendor_onboarding` config

The dynamic percentage calculation applies to the **earning (money) path only**. It replaces the fixed `registration_bonus` amount with the dynamically calculated GHS amount. The points path for customer/vendor referrers remains unchanged — they still receive the configured points value. The percentage settings for customer and vendor categories are stored and displayed in the admin UI for consistency and future use, but do not affect the points-based reward until a future decision is made to unify the two paths.

### Transition / Backward Compatibility

- The `registration_bonus` column on `referral_codes` is NOT removed
- New codes leave it as 0/null
- Old codes with a non-zero `registration_bonus` value: that stored value is honored as a fallback
- Logic: if `registration_bonus > 0`, use it; otherwise, use dynamic calculation
- This provides a clean transition with no data loss

### Display Changes

**Referral codes index/show (admin):**
- Codes with stored `registration_bonus > 0`: show that value (legacy)
- Codes with 0/null: show computed values — "Tier 1: GHS X / Tier 2: GHS Y"

**Single code creation form (admin):**
- Remove manual `registration_bonus` input field
- Show read-only computed preview based on the selected user's role

**MyReferralController response (mobile):**
- Include computed bonus values for both tiers based on the user's role

---

## Files Changed

### Backend

| File | Change |
|------|--------|
| `database/migrations/XXXX_add_referral_bonus_percentage_settings.php` | Seed 5 new settings |
| `app/Models/ReferralCode.php` | Add `ROLE_PREFIXES` constant, update `generateUniqueCode()` for prefix support, add `getPrefixForRole()` |
| `app/Services/ReferralService.php` | Add `bulkGenerateCodes()`, update `activateReferral()` for dynamic calculation, update `createReferralCode()` to accept prefix |
| `app/Http/Controllers/ReferralCodeController.php` (web) | Add `bulkGenerate()` and `bulkGeneratePreview()` actions |
| `app/Http/Controllers/Api/V1/ReferralCodeController.php` | Prefix codes with user's role on creation |
| `app/Http/Controllers/Api/V1/MyReferralController.php` | Prefix auto-created codes, include computed bonus in response |
| `app/Http/Controllers/Settings/VendorOnboardingController.php` | Include/validate 5 new percentage settings |
| `app/Http/Requests/BulkGenerateReferralCodeRequest.php` | New — validates role for bulk generation |
| `app/Http/Requests/StoreReferralCodeRequest.php` | Remove `registration_bonus` validation |
| `routes/web.php` | Add bulk generate preview + execute routes |

### Frontend

| File | Change |
|------|--------|
| `resources/js/Pages/settings/vendor-onboarding.tsx` | Add Referral Bonus Percentages section with live calculation |
| Referral codes index page | Add "Bulk Generate" button and modal |
| Referral codes create page | Remove manual bonus input, show computed preview |
| Referral codes index table | Show computed tier values for dynamic codes |

### Tests

| Test | Covers |
|------|--------|
| Bulk generation | Correct count created, skips users with existing codes, correct prefix per role, transaction rollback on failure |
| Dynamic bonus calculation | Correct amount for each category × tier combination, fallback to stored value for legacy codes |
| Settings | CRUD for 5 percentage settings, validation (0-100 range), cache invalidation |
| Prefix system | Correct prefix applied per role, uniqueness maintained with prefix |

---

## Out of Scope

- Migrating existing codes to add prefixes
- Admin/super_admin roles getting referral codes (they are not in the 5 eligible categories)
- Changing the points-based reward system (points_per_vendor_onboarding config unchanged)
- Commission rate changes (separate from registration bonus)
