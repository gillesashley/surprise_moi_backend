# Vendor Tier Upgrade API Documentation

**Base URL:** `https://dashboard.surprisemoi.com/api/v1`

**Auth:** All vendor endpoints require `Authorization: Bearer <token>` (Sanctum). Webhook and callback are public.

---

## Flow Overview

```
Tier 2 Vendor
    |
    v
[1] GET /vendor/upgrade-tier/summary
    (shows upgrade fee, current status)
    |
    v
[2] POST /vendor/upgrade-tier/payment/initiate
    (returns Paystack authorization_url)
    |
    v
[3] Vendor pays via Paystack (in-app webview or redirect)
    |
    v
[4] POST /vendor/upgrade-tier/payment/verify
    (confirm payment with reference)
    |
    v
[5] POST /vendor/upgrade-tier/submit-document
    (upload business certificate)
    |
    v
[6] GET /vendor/upgrade-tier/status
    (poll for admin decision)
    |
    v
Admin approves or rejects
    |
    +--> Approved: vendor_tier becomes 1
    +--> Rejected: vendor can resubmit document (back to step 5)
```

**If rejected:** The vendor does NOT pay again. They resubmit a new document using the same endpoint (step 5). The status goes back to `pending_review`.

**Cancel:** If the vendor starts but never pays, they can cancel with `DELETE /vendor/upgrade-tier/cancel` and start over. Stale unpaid requests auto-expire after 24 hours.

---

## Status Values

| Status | Meaning |
|--------|---------|
| `pending_payment` | Request created, waiting for Paystack payment |
| `pending_document` | Payment verified, waiting for document upload |
| `pending_review` | Document submitted, waiting for admin review |
| `approved` | Admin approved, vendor is now Tier 1 |
| `rejected` | Admin rejected, vendor can resubmit document |

---

## Endpoints

### 1. Get Upgrade Summary

```
GET /vendor/upgrade-tier/summary
```

Returns the upgrade fee and any existing request. Use this to show the "Upgrade to Tier 1" screen.

**Response (no existing request):**
```json
{
  "success": true,
  "data": {
    "upgrade_fee": 50.0,
    "currency": "GHS",
    "current_tier": 2,
    "existing_request": null
  }
}
```

**Response (existing rejected request):**
```json
{
  "success": true,
  "data": {
    "upgrade_fee": 50.0,
    "currency": "GHS",
    "current_tier": 2,
    "existing_request": {
      "id": 1,
      "status": "rejected",
      "payment_amount": 50.0,
      "currency": "GHS",
      "payment_verified_at": "2026-03-13T12:00:00.000000Z",
      "business_certificate_document": "https://storage.example.com/tier-upgrades/...",
      "admin_notes": "Document is illegible, please resubmit a clearer copy",
      "reviewed_at": "2026-03-13T14:00:00.000000Z",
      "created_at": "2026-03-13T11:00:00.000000Z",
      "updated_at": "2026-03-13T14:00:00.000000Z"
    }
  }
}
```

**Frontend logic:**
- If `existing_request` is `null` → show "Pay to upgrade" button
- If `existing_request.status` is `pending_payment` → show "Complete payment" or "Cancel" option
- If `existing_request.status` is `pending_document` → show document upload form
- If `existing_request.status` is `pending_review` → show "Under review" message
- If `existing_request.status` is `rejected` → show rejection reason + "Resubmit document" button
- If `current_tier` is `1` → this endpoint returns 403, don't show upgrade UI

**Error (not Tier 2):**
```json
{
  "success": false,
  "message": "Only Tier 2 vendors can upgrade."
}
```
Status: `403`

---

### 2. Initiate Payment

```
POST /vendor/upgrade-tier/payment/initiate
```

No request body needed. Creates a Paystack payment and returns the authorization URL.

**Rate limit:** 5 requests per minute.

**Success Response (201):**
```json
{
  "success": true,
  "message": "Payment initialized successfully.",
  "data": {
    "authorization_url": "https://checkout.paystack.com/xxxxxxxxxx",
    "access_code": "xxxxxxxxxx",
    "reference": "TUP-ABCDEFGHIJKLMNOP",
    "amount": 50.0,
    "currency": "GHS"
  }
}
```

**Frontend:** Open `authorization_url` in a webview or redirect. After payment, Paystack redirects to the callback URL. You can also verify manually using the reference.

**Error (already has active request):**
```json
{
  "success": false,
  "message": "You already have an active upgrade request."
}
```
Status: `409`

---

### 3. Verify Payment

```
POST /vendor/upgrade-tier/payment/verify
```

Call this after the vendor completes payment to confirm it was successful.

**Rate limit:** 10 requests per minute.

**Request:**
```json
{
  "reference": "TUP-ABCDEFGHIJKLMNOP"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment verified successfully. Please submit your business certificate.",
  "data": {
    "request_id": 1,
    "status": "pending_document"
  }
}
```

**Error (payment failed):**
```json
{
  "success": false,
  "message": "Payment was not successful: Insufficient Funds"
}
```
Status: `400`

**Error (not found):**
```json
{
  "success": false,
  "message": "Upgrade request not found for this reference."
}
```
Status: `404`

---

### 4. Payment Callback (Public)

```
GET /vendor/upgrade-tier/payment/callback?reference=TUP-ABCDEFGHIJKLMNOP
```

Paystack redirects here after browser/webview payment. This endpoint verifies the payment automatically. No auth required.

**Note:** The webhook also handles payment verification server-to-server, so the payment may already be verified by the time the callback is hit.

---

### 5. Submit Business Certificate Document

```
POST /vendor/upgrade-tier/submit-document
Content-Type: multipart/form-data
```

Upload the business certificate. Also used for **resubmission after rejection** (same endpoint, no extra payment).

**Rate limit:** 5 requests per minute.

**Request (multipart form data):**

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `business_certificate_document` | file | yes | jpeg, png, jpg, or pdf. Max 10MB. |

**Success Response (200):**
```json
{
  "success": true,
  "message": "Document submitted successfully. Your request is now under review.",
  "data": {
    "id": 1,
    "status": "pending_review",
    "payment_amount": 50.0,
    "currency": "GHS",
    "payment_verified_at": "2026-03-13T12:00:00.000000Z",
    "business_certificate_document": "https://storage.example.com/tier-upgrades/business-certificates/5/cert.pdf",
    "admin_notes": null,
    "reviewed_at": null,
    "created_at": "2026-03-13T11:00:00.000000Z",
    "updated_at": "2026-03-13T13:00:00.000000Z"
  }
}
```

**On resubmission (after rejection):**
- `admin_notes` is cleared to `null`
- `reviewed_at` is cleared to `null`
- Status changes from `rejected` back to `pending_review`

**Validation Error (422):**
```json
{
  "message": "The business certificate must be a JPEG, PNG, JPG, or PDF file.",
  "errors": {
    "business_certificate_document": [
      "The business certificate must be a JPEG, PNG, JPG, or PDF file."
    ]
  }
}
```

**Error (wrong status):**
```json
{
  "success": false,
  "message": "No eligible upgrade request found for document submission."
}
```
Status: `422`

---

### 6. Check Upgrade Status

```
GET /vendor/upgrade-tier/status
```

Returns the current upgrade request. Use this to poll for admin decisions.

**Response (has active request):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "pending_review",
    "payment_amount": 50.0,
    "currency": "GHS",
    "payment_verified_at": "2026-03-13T12:00:00.000000Z",
    "business_certificate_document": "https://storage.example.com/tier-upgrades/...",
    "admin_notes": null,
    "reviewed_at": null,
    "created_at": "2026-03-13T11:00:00.000000Z",
    "updated_at": "2026-03-13T13:00:00.000000Z"
  }
}
```

**Response (no active request):**
```json
{
  "success": true,
  "data": null
}
```

**After approval:** This returns `null` (approved requests are no longer "active"). The vendor's `vendor_tier` in the profile response will now be `1`.

**After rejection:** Returns the request with `status: "rejected"`, `admin_notes` with the reason, and `reviewed_at` with the timestamp.

---

### 7. Cancel Upgrade Request

```
DELETE /vendor/upgrade-tier/cancel
```

Cancels an unpaid upgrade request. Only works when status is `pending_payment`.

**Success Response (200):**
```json
{
  "success": true,
  "message": "Upgrade request cancelled."
}
```

**Error (not cancellable):**
```json
{
  "success": false,
  "message": "No cancellable upgrade request found."
}
```
Status: `422`

---

## Recommended Flutter UI Flow

### Screen: Upgrade Bottom Sheet

1. Call `GET /summary` on open
2. Based on response:

| Scenario | UI |
|----------|----|
| `existing_request` is `null` | Show upgrade fee (e.g., "Upgrade to Tier 1 for GHS 50.00") + "Pay Now" button |
| `status: pending_payment` | Show "Complete Payment" button + "Cancel" option |
| `status: pending_document` | Show document upload form |
| `status: pending_review` | Show "Under Review" with a spinner/pending state |
| `status: rejected` | Show rejection reason from `admin_notes` + "Resubmit Document" button |

### Payment Flow

1. Tap "Pay Now" → call `POST /payment/initiate`
2. Open `authorization_url` in Paystack webview
3. On Paystack completion → call `POST /payment/verify` with the reference
4. On success → show document upload form

### Document Upload Flow

1. Pick file (jpeg/png/jpg/pdf, max 10MB)
2. Call `POST /submit-document` with multipart form data
3. On success → show "Under Review" state

### Polling for Decision

After document submission, periodically call `GET /status` (or use push notifications) to check if admin has reviewed:
- `status: approved` → show success, refresh profile to get `vendor_tier: 1`
- `status: rejected` → show `admin_notes` reason + resubmit option

---

## Notifications

Vendors receive push/database notifications for:
- **Approved:** "Your Tier 1 upgrade has been approved"
- **Rejected:** "Your Tier 1 upgrade has been rejected: {reason}"

---

## Key Rules

- **One-way upgrade:** Tier 2 → Tier 1 only. No downgrade.
- **No refunds:** Payment is non-refundable, even on rejection.
- **Unlimited resubmissions:** After rejection, vendor can resubmit documents without paying again.
- **One active request:** A vendor can only have one upgrade request at a time.
- **Auto-expiry:** Unpaid requests expire after 24 hours.
- **Upgrade fee:** Difference between Tier 1 and Tier 2 onboarding fees (currently GHS 50.00, configurable by admin).
