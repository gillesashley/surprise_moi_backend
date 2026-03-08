# Mobile Money Verification - API Changes for Mobile App

## Overview

Mobile money payment methods are now verified through Paystack when saved. Previously, mobile money was saved without verification (status stayed "Pending verification" forever). Now it works the same as bank transfers — Paystack verifies the account and provides a recipient code for automated payouts.

## What Changed

### 1. `POST /api/v1/vendor/payout-details` (Save Mobile Money)

**Before:** Mobile money was always saved successfully with `is_verified: false`.

**Now:** Mobile money is verified through Paystack before saving.

- **On success:** Returns `201` with `is_verified: true` and a `paystack_recipient_code`
- **On failure:** Returns `422` with an error message (e.g., invalid phone number, unsupported provider)

**Request body** — unchanged:
```json
{
  "payout_method": "mobile_money",
  "account_number": "0244123456",
  "bank_code": "MTN",
  "account_name": "Kwame Asante",
  "provider": "mtn"
}
```

**Success response (201):**
```json
{
  "success": true,
  "message": "Mobile money payout details saved and verified successfully.",
  "payout_detail": {
    "id": 1,
    "payout_method": "mobile_money",
    "account_name": "Kwame Asante",
    "account_number": "0244123456",
    "bank_code": "MTN",
    "bank_name": "MTN Mobile Money",
    "is_verified": true,
    "is_default": true,
    "paystack_recipient_code": "RCP_xxx..."
  }
}
```

**Error response (422):**
```json
{
  "success": false,
  "message": "Could not verify mobile money account. Please check your details."
}
```

#### Mobile App Action Required

- Handle `422` errors from this endpoint for mobile money (same as bank transfers)
- Show the error message to the user
- Remove "Pending verification" status — verification now happens immediately at save time
- On success, the payment method is fully verified (show "Verified" status)

### 2. New Endpoint: `GET /api/v1/vendor/payout-details/mobile-money-providers`

Returns the list of supported mobile money providers from Paystack. Use this to populate the provider selection dropdown instead of hardcoding providers.

**Response (200):**
```json
{
  "success": true,
  "providers": [
    {
      "name": "MTN",
      "code": "MTN",
      "type": "mobile_money"
    },
    {
      "name": "Vodafone",
      "code": "VOD",
      "type": "mobile_money"
    },
    {
      "name": "AirtelTigo",
      "code": "ATL",
      "type": "mobile_money"
    }
  ]
}
```

#### Mobile App Action Required

- Call this endpoint to get the list of mobile money providers
- Use the `code` field as the `bank_code` value when saving payout details
- This ensures the codes match what Paystack expects

### 3. Payouts Now Work for Mobile Money

Previously, automated payouts via Paystack only worked for bank transfers (because mobile money had no `paystack_recipient_code`). Now that mobile money details are verified through Paystack, the admin can process payouts for mobile money the same way as bank transfers — no changes needed on the mobile app side for this.

## Summary of Mobile App Changes

| Change | Priority | Description |
|--------|----------|-------------|
| Handle 422 errors on save | **Required** | Show Paystack verification errors to user |
| Remove "Pending verification" | **Required** | Verification is now instant — show "Verified" on success |
| Use mobile money providers endpoint | **Recommended** | Fetch providers from Paystack instead of hardcoding |
| No payout flow changes | None | Payouts work automatically with verified details |
