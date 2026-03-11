# Surprise Moi — Rider App API Guide (Flutter Team)

**Version:** 1.0
**Base URL:** `https://dashboard.surprisemoi.com/api/rider/v1`
**Auth:** Bearer Token (Laravel Sanctum)
**Currency:** GHS (Ghana Cedis)

---

## Table of Contents

1. [Authentication Flow](#1-authentication-flow)
2. [Onboarding Flow](#2-onboarding-flow)
3. [Dashboard](#3-dashboard)
4. [Delivery Flow](#4-delivery-flow)
5. [Earnings & Withdrawals](#5-earnings--withdrawals)
6. [Profile Management](#6-profile-management)
7. [Real-time Features](#7-real-time-features)
8. [Error Handling](#8-error-handling)
9. [UI Screen Map](#9-ui-screen-map)

---

## General Notes

### Response Format

All responses follow this structure:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { ... }
}
```

Error responses:
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Authentication Header

All authenticated requests must include:
```
Authorization: Bearer <token>
```

### Pagination

Paginated endpoints return:
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

Use `?page=2&per_page=20` query params to paginate.

---

## 1. Authentication Flow

### 1.1 Register

**POST** `/auth/register`

Creates a new rider account. Rider starts in `pending` status and must submit documents before they can receive deliveries.

**Request:**
```json
{
  "name": "Kwame Asante",
  "email": "kwame@example.com",
  "phone": "0241234567",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "vehicle_category": "motorbike"
}
```

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `name` | string | Yes | max 255 chars |
| `email` | string | Yes | valid email, unique |
| `phone` | string | Yes | max 20 chars, unique |
| `password` | string | Yes | min 8 chars |
| `password_confirmation` | string | Yes | must match password |
| `vehicle_category` | string | Yes | `motorbike` or `car` |

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful. Please upload your documents for verification.",
  "data": {
    "rider": {
      "id": 1,
      "name": "Kwame Asante",
      "email": "kwame@example.com",
      "phone": "0241234567",
      "vehicle_category": "motorbike",
      "status": "pending",
      "is_online": false,
      "average_rating": 0,
      "total_deliveries": 0,
      "phone_verified_at": null,
      "email_verified_at": null,
      "created_at": "2026-03-11T10:00:00.000Z"
    },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

### 1.2 Login

**POST** `/auth/login`

Login with email or phone + password.

**Request (email):**
```json
{
  "email": "kwame@example.com",
  "password": "SecurePass123!"
}
```

**Request (phone):**
```json
{
  "phone": "0241234567",
  "password": "SecurePass123!"
}
```

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `email` | string | Required if no phone | valid email |
| `phone` | string | Required if no email | string |
| `password` | string | Yes | string |

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "rider": { ... },
    "token": "2|xyz789...",
    "token_type": "Bearer"
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "message": "Invalid credentials."
}
```

### 1.3 Send OTP

**POST** `/auth/otp/send`

```json
{
  "phone": "0241234567"
}
```

### 1.4 Verify OTP

**POST** `/auth/otp/verify`

```json
{
  "phone": "0241234567",
  "otp": "123456"
}
```

### 1.5 Forgot Password

**POST** `/auth/forgot-password`

```json
{
  "email": "kwame@example.com"
}
```

### 1.6 Reset Password

**POST** `/auth/reset-password`

```json
{
  "email": "kwame@example.com",
  "token": "reset-token-from-email",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}
```

### 1.7 Logout

**POST** `/auth/logout` *(authenticated)*

No body required. Revokes current token.

---

## 2. Onboarding Flow

After registration, the rider must submit documents for admin review. The rider cannot access delivery features until their status is `approved`.

### Rider Status Lifecycle

```
pending → under_review → approved ✓
                       → rejected → (resubmit) → under_review → ...
approved → suspended (by admin)
```

### 2.1 Submit Documents

**POST** `/onboarding/documents` *(authenticated)*

**Content-Type:** `multipart/form-data`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `ghana_card_front` | file | Yes | image, max 5MB |
| `ghana_card_back` | file | Yes | image, max 5MB |
| `drivers_license` | file | Yes | image, max 5MB |
| `vehicle_photo` | file | Yes | image, max 5MB |
| `vehicle_type` | string | Yes | e.g., "Honda CG 125" |
| `license_plate` | string | Yes | e.g., "GR-1234-21" |

**Response (200):**
```json
{
  "success": true,
  "message": "Documents submitted for review.",
  "data": {
    "id": 1,
    "status": "under_review",
    ...
  }
}
```

### 2.2 Check Onboarding Status

**GET** `/onboarding/status` *(authenticated)*

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "under_review",
    "has_documents": true,
    "rider": { ... }
  }
}
```

### 2.3 Resubmit Documents (after rejection)

**PUT** `/onboarding/documents` *(authenticated)*

Same fields as submit. Only allowed when status is `rejected`.

### Flutter UI Notes — Onboarding
- After registration, navigate to document upload screen
- Show a camera/gallery picker for each document
- After submission, show a "Waiting for Approval" screen with status polling
- Poll `GET /onboarding/status` every 30 seconds, or use push notification to detect approval
- If rejected, show reason and allow resubmission
- Once approved, navigate to the main dashboard

---

## 3. Dashboard

All dashboard endpoints require `approved` status.

### 3.1 Get Dashboard

**GET** `/dashboard` *(authenticated, approved)*

**Response:**
```json
{
  "success": true,
  "data": {
    "is_online": true,
    "today_earnings": 125.50,
    "today_deliveries": 5,
    "total_earnings": 3450.00,
    "total_deliveries": 142,
    "average_rating": 4.75,
    "available_balance": 890.00,
    "pending_balance": 125.50,
    "active_delivery": null
  }
}
```

If there's an active delivery, `active_delivery` contains the full delivery request object (same as delivery detail response).

### 3.2 Toggle Online/Offline

**POST** `/dashboard/toggle-online` *(authenticated, approved)*

No body required. Toggles the rider's online status.

**Response:**
```json
{
  "success": true,
  "message": "You are now online.",
  "data": {
    "is_online": true
  }
}
```

### 3.3 Update Location

**POST** `/dashboard/location` *(authenticated, approved)*

**Call this every 10-15 seconds when the rider is online or has an active delivery.**

```json
{
  "latitude": 5.6037,
  "longitude": -0.1870
}
```

**Response:**
```json
{
  "success": true
}
```

### 3.4 Update Device Token (FCM)

**PUT** `/dashboard/device-token` *(authenticated, approved)*

```json
{
  "device_token": "fcm-token-string..."
}
```

### Flutter UI Notes — Dashboard
- Show a prominent online/offline toggle at the top
- Display today's stats (earnings, deliveries)
- Show available balance prominently
- If there's an active delivery, show it as a card/banner with "Continue Delivery" action
- Start a background location service when online
- Send location updates every 10-15 seconds via `POST /dashboard/location`
- Listen for FCM push notifications for incoming delivery requests

---

## 4. Delivery Flow

### Delivery Request Lifecycle

```
broadcasting → accepted → picked_up → in_transit → delivered ✓
           → expired (no rider accepted)
           → cancelled (rider or vendor cancels)

assigned → accepted → ... (same as above)
        → declined → broadcasting → ...
```

### 4.1 Get Incoming Delivery Requests

**GET** `/deliveries/incoming` *(authenticated, approved)*

Returns delivery requests that are being broadcast to this rider or directly assigned.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid-here",
      "order_id": 42,
      "status": "broadcasting",
      "pickup_address": "Osu Oxford Street, Shop 12",
      "pickup_latitude": 5.5571,
      "pickup_longitude": -0.1818,
      "dropoff_address": "East Legon, American House",
      "dropoff_latitude": 5.6350,
      "dropoff_longitude": -0.1520,
      "delivery_fee": 25.00,
      "distance_km": 8.50,
      "expires_at": "2026-03-11T10:00:30.000Z",
      "vendor": {
        "id": 5,
        "name": "Surprise Gift Shop",
        "phone": "0201234567"
      },
      "created_at": "2026-03-11T10:00:00.000Z"
    }
  ]
}
```

### 4.2 Accept a Delivery

**POST** `/deliveries/{delivery_request_id}/accept` *(authenticated, approved)*

No body required.

**Response (200):**
```json
{
  "success": true,
  "message": "Delivery accepted. Navigate to pickup location.",
  "data": {
    "id": "uuid-here",
    "status": "accepted",
    "accepted_at": "2026-03-11T10:00:15.000Z",
    "order": {
      "order_number": "ORD-ABC1234567",
      "receiver_name": "Ama Mensah",
      "receiver_phone": "0241112233",
      "special_instructions": "Call when you arrive at the gate"
    },
    "vendor": {
      "id": 5,
      "name": "Surprise Gift Shop",
      "phone": "0201234567"
    },
    ...
  }
}
```

**Error (409) — Already accepted by another rider:**
```json
{
  "success": false,
  "message": "This delivery has already been accepted."
}
```

### 4.3 Decline a Delivery

**POST** `/deliveries/{delivery_request_id}/decline` *(authenticated, approved)*

No body required. If this was an assigned delivery, it falls back to open broadcast.

### 4.4 Get Active Delivery

**GET** `/deliveries/active` *(authenticated, approved)*

Returns the rider's current active delivery (status: accepted, picked_up, or in_transit).

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-here",
    "status": "accepted",
    "pickup_address": "...",
    "dropoff_address": "...",
    "delivery_fee": 25.00,
    "order": {
      "order_number": "ORD-ABC1234567",
      "receiver_name": "Ama Mensah",
      "receiver_phone": "0241112233",
      "delivery_pin": "4821",
      "special_instructions": "Call when you arrive at the gate"
    },
    "vendor": {
      "id": 5,
      "name": "Surprise Gift Shop",
      "phone": "0201234567"
    }
  }
}
```

**Note:** `delivery_pin` is only included for active deliveries (not in history).

### 4.5 Confirm Pickup

**POST** `/deliveries/{delivery_request_id}/pickup` *(authenticated, approved)*

Call this when the rider arrives at the vendor and picks up the package.

No body required.

**Response:**
```json
{
  "success": true,
  "message": "Pickup confirmed. Navigate to delivery location.",
  "data": {
    "status": "picked_up",
    "picked_up_at": "2026-03-11T10:15:00.000Z"
  }
}
```

### 4.6 Confirm Delivery

**POST** `/deliveries/{delivery_request_id}/deliver` *(authenticated, approved)*

The rider must enter the 4-digit PIN provided by the customer to confirm delivery.

```json
{
  "delivery_pin": "4821"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Delivery confirmed! Earnings credited.",
  "data": {
    "status": "delivered",
    "delivered_at": "2026-03-11T10:30:00.000Z",
    "earning": {
      "amount": 25.00,
      "status": "pending",
      "available_at": "2026-03-12T10:30:00.000Z"
    }
  }
}
```

**Error (422) — Wrong PIN:**
```json
{
  "success": false,
  "message": "Invalid delivery PIN."
}
```

### 4.7 Cancel Delivery

**POST** `/deliveries/{delivery_request_id}/cancel` *(authenticated, approved)*

```json
{
  "reason": "Vehicle breakdown"
}
```

| Field | Type | Required |
|-------|------|----------|
| `reason` | string | Yes, max 500 chars |

### 4.8 Delivery History

**GET** `/deliveries/history?page=1&per_page=20` *(authenticated, approved)*

Returns completed/cancelled deliveries, paginated, newest first.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "status": "delivered",
      "pickup_address": "...",
      "dropoff_address": "...",
      "delivery_fee": 25.00,
      "distance_km": 8.50,
      "accepted_at": "2026-03-11T10:00:15.000Z",
      "delivered_at": "2026-03-11T10:30:00.000Z",
      "created_at": "2026-03-11T10:00:00.000Z"
    }
  ],
  "meta": { ... }
}
```

### 4.9 Delivery Detail

**GET** `/deliveries/{delivery_request_id}` *(authenticated, approved)*

Returns full details of a specific delivery.

### Flutter UI Notes — Delivery Flow

**Incoming Request Popup:**
- When a FCM push notification arrives with type `new_delivery_request`, show a bottom sheet / modal
- Display: pickup address, dropoff address, delivery fee, distance, countdown timer
- Two buttons: "Accept" and "Decline"
- Countdown from `expires_at` — auto-dismiss when expired
- Sound/vibration alert

**Active Delivery Screen (4 stages):**

```
Stage 1: ACCEPTED — "Navigate to Pickup"
├── Show vendor name, address, phone (tap to call)
├── "Open in Google Maps" button for navigation
├── "Confirm Pickup" button at bottom
│
Stage 2: PICKED_UP — "Navigate to Customer"
├── Show customer name, address, phone
├── Show special instructions
├── "Open in Google Maps" button
├── "Confirm Delivery" button at bottom
│
Stage 3: DELIVERY CONFIRMATION
├── Show PIN input field (4 digits)
├── "The customer will give you this PIN"
├── "Confirm" button
│
Stage 4: DELIVERED — "Delivery Complete!"
├── Show earnings summary
├── "Back to Home" button
```

**During active delivery:**
- Send location updates every 10 seconds via `POST /dashboard/location`
- Keep screen awake
- Show a floating notification/banner if the app is backgrounded

---

## 5. Earnings & Withdrawals

### 5.1 Get Balance Summary

**GET** `/earnings` *(authenticated, approved)*

**Response:**
```json
{
  "success": true,
  "data": {
    "available": 890.00,
    "pending": 125.50,
    "total_earned": 3450.00,
    "total_withdrawn": 2434.50
  }
}
```

- `available` — can be withdrawn now
- `pending` — earned but in 24-hour hold period (credited after delivery, available next day)
- `total_earned` — all-time earnings
- `total_withdrawn` — all-time withdrawals

### 5.2 Transaction History

**GET** `/earnings/transactions?page=1&per_page=20` *(authenticated, approved)*

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "order_id": 42,
      "amount": 25.00,
      "type": "delivery_fee",
      "status": "available",
      "available_at": "2026-03-12T10:30:00.000Z",
      "created_at": "2026-03-11T10:30:00.000Z"
    }
  ],
  "meta": { ... }
}
```

### 5.3 Request Withdrawal

**POST** `/earnings/withdraw` *(authenticated, approved)*

```json
{
  "amount": 500.00,
  "mobile_money_provider": "mtn",
  "mobile_money_number": "0241234567"
}
```

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `amount` | number | Yes | min 1, max available balance |
| `mobile_money_provider` | string | Yes | `mtn`, `vodafone`, or `airteltigo` |
| `mobile_money_number` | string | Yes | valid phone number |

**Response (201):**
```json
{
  "success": true,
  "message": "Withdrawal request submitted. Processing takes 1-24 hours.",
  "data": {
    "id": "uuid",
    "amount": 500.00,
    "status": "pending",
    "mobile_money_provider": "mtn",
    "mobile_money_number": "0241234567",
    "created_at": "2026-03-11T12:00:00.000Z"
  }
}
```

**Error (422) — Insufficient balance:**
```json
{
  "success": false,
  "message": "Insufficient available balance."
}
```

### 5.4 Withdrawal History

**GET** `/earnings/withdrawals?page=1&per_page=20` *(authenticated, approved)*

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "amount": 500.00,
      "status": "completed",
      "mobile_money_provider": "mtn",
      "mobile_money_number": "0241234567",
      "processed_at": "2026-03-11T14:00:00.000Z",
      "created_at": "2026-03-11T12:00:00.000Z"
    }
  ],
  "meta": { ... }
}
```

Withdrawal statuses: `pending` → `processing` → `completed` / `rejected` / `failed`

### Flutter UI Notes — Earnings
- Main earnings screen: show available balance large, pending below
- "Withdraw" button opens a form with amount, MoMo provider dropdown, number input
- Transaction list below with pull-to-refresh
- Tab for "Withdrawals" showing withdrawal request history with status badges
- Color code statuses: green=completed, yellow=pending, red=failed/rejected

---

## 6. Profile Management

### 6.1 Get Profile

**GET** `/profile` *(authenticated, approved)*

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Kwame Asante",
    "email": "kwame@example.com",
    "phone": "0241234567",
    "vehicle_type": "Honda CG 125",
    "vehicle_category": "motorbike",
    "license_plate": "GR-1234-21",
    "status": "approved",
    "is_online": false,
    "average_rating": 4.75,
    "total_deliveries": 142,
    "phone_verified_at": "2026-03-11T10:00:00.000Z",
    "email_verified_at": "2026-03-11T10:00:00.000Z",
    "created_at": "2026-03-11T10:00:00.000Z"
  }
}
```

### 6.2 Update Profile

**PUT** `/profile` *(authenticated, approved)*

```json
{
  "name": "Kwame Asante Jr",
  "email": "kwame.jr@example.com",
  "phone": "0241234568"
}
```

All fields optional. Email/phone checked for uniqueness.

### 6.3 Update Vehicle

**PUT** `/profile/vehicle` *(authenticated, approved)*

```json
{
  "vehicle_type": "Toyota Corolla",
  "vehicle_category": "car",
  "license_plate": "GR-5678-22"
}
```

### 6.4 Change Password

**PUT** `/profile/password` *(authenticated, approved)*

```json
{
  "current_password": "OldPass123!",
  "password": "NewPass123!",
  "password_confirmation": "NewPass123!"
}
```

---

## 7. Real-time Features

### 7.1 Push Notifications (FCM)

Register the device token after login:
```
PUT /dashboard/device-token
{ "device_token": "fcm-token..." }
```

**FCM Notification Types:**

| Type | When | Action |
|------|------|--------|
| `new_delivery_request` | New delivery broadcast | Show accept/decline popup |
| `delivery_assigned` | Vendor assigned you specifically | Show accept/decline popup |
| `delivery_cancelled` | Vendor cancelled the delivery | Dismiss active delivery |
| `account_approved` | Admin approved your account | Navigate to dashboard |
| `account_rejected` | Admin rejected your account | Show rejection reason |
| `withdrawal_processed` | Withdrawal completed | Show notification |

**FCM Payload Structure:**
```json
{
  "notification": {
    "title": "New Delivery Request",
    "body": "GHS 25.00 — Osu to East Legon (8.5km)"
  },
  "data": {
    "type": "new_delivery_request",
    "delivery_request_id": "uuid-here",
    "pickup_address": "Osu Oxford Street",
    "dropoff_address": "East Legon",
    "delivery_fee": "25.00",
    "distance_km": "8.50",
    "expires_at": "2026-03-11T10:00:30.000Z"
  }
}
```

### 7.2 WebSocket (Laravel Reverb)

For live delivery tracking, connect to the WebSocket server.

**WebSocket URL:** `wss://dashboard.surprisemoi.com/app/{app-key}`

**Channels to subscribe:**

| Channel | Purpose |
|---------|---------|
| `delivery.{delivery_request_id}` | Delivery status updates |
| `private-rider.{rider_id}` | Personal notifications |

**Events:**

| Event | Channel | Data |
|-------|---------|------|
| `DeliveryStatusUpdated` | `delivery.*` | `{ status, rider_latitude, rider_longitude }` |

**Flutter Implementation:**
- Use `laravel_echo` or `pusher_client` Dart package
- Connect when rider is online
- Subscribe to personal channel for real-time updates

---

## 8. Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created (registration, withdrawal request) |
| 401 | Unauthenticated — token missing, invalid, or expired |
| 403 | Forbidden — account not approved, suspended, or wrong role |
| 404 | Resource not found |
| 409 | Conflict — delivery already accepted by another rider |
| 422 | Validation error — check `errors` field |
| 429 | Rate limited — too many requests |
| 500 | Server error |

### Common Error Responses

**Unauthenticated (401):**
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

**Account Pending (403):**
```json
{
  "success": false,
  "message": "Your account is pending approval.",
  "data": {
    "status": "pending"
  }
}
```

**Account Suspended (403):**
```json
{
  "success": false,
  "message": "Your account has been suspended. Please contact support."
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Flutter Error Handling Strategy
- Check `success` field first, not HTTP status code
- For 401: clear stored token, navigate to login screen
- For 403 with `status` in data: navigate to appropriate screen (pending/suspended)
- For 422: display field-specific errors under form inputs
- For 429: show "Please wait" and retry after delay
- For 500: show generic "Something went wrong" with retry option

---

## 9. UI Screen Map

### App Navigation Structure

```
┌─────────────────────────────────────────┐
│              SPLASH SCREEN              │
│         (check stored token)            │
│    ┌──────────┬──────────────┐          │
│    ↓          ↓              ↓          │
│  LOGIN    REGISTER     MAIN APP        │
│    │          │         (if token       │
│    │          ↓          valid)         │
│    │    DOCUMENT                        │
│    │    UPLOAD                          │
│    │          │                         │
│    │          ↓                         │
│    │    WAITING FOR                     │
│    │    APPROVAL                        │
│    │          │                         │
│    ↓          ↓                         │
│         MAIN APP                        │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│              MAIN APP                   │
│  ┌─────────────────────────────┐        │
│  │   Bottom Navigation Bar     │        │
│  ├──────┬──────┬───────┬──────┤        │
│  │ Home │Deliv.│Earnings│Profile│       │
│  └──┬───┴──┬───┴───┬───┴──┬───┘       │
│     ↓      ↓       ↓      ↓            │
│  DASHBOARD DELIVERY EARNINGS PROFILE   │
│  - Toggle  HISTORY  - Balance - Info    │
│  - Stats   - List   - Trans. - Vehicle │
│  - Active  - Detail - Withdraw- Password│
│    Delivery                             │
│                                         │
│  [OVERLAY] INCOMING DELIVERY POPUP      │
│  - Accept / Decline with countdown      │
│                                         │
│  [FULLSCREEN] ACTIVE DELIVERY           │
│  - Stage 1: Navigate to pickup          │
│  - Stage 2: Navigate to customer        │
│  - Stage 3: Enter PIN                   │
│  - Stage 4: Delivery complete           │
└─────────────────────────────────────────┘
```

### Screen Details

| Screen | API Calls | Key Features |
|--------|-----------|--------------|
| **Splash** | Validate stored token via `GET /profile` | Auto-login or redirect to auth |
| **Login** | `POST /auth/login` | Email/phone toggle, remember me |
| **Register** | `POST /auth/register` | Step form: info → vehicle category |
| **Document Upload** | `POST /onboarding/documents` | Camera/gallery for 4 documents |
| **Waiting for Approval** | `GET /onboarding/status` (poll) | Animated waiting state, FCM listener |
| **Dashboard** | `GET /dashboard`, `POST /dashboard/toggle-online` | Online toggle, stats cards, active delivery banner |
| **Incoming Request** | `POST /deliveries/{id}/accept` or `/decline` | Bottom sheet, countdown timer, fee display |
| **Active Delivery** | `GET /deliveries/active`, status update endpoints | Multi-stage flow, Google Maps integration |
| **Delivery History** | `GET /deliveries/history` | Paginated list with pull-to-refresh |
| **Earnings** | `GET /earnings`, `GET /earnings/transactions` | Balance card, transaction list |
| **Withdraw** | `POST /earnings/withdraw` | Amount input, MoMo provider picker |
| **Withdrawal History** | `GET /earnings/withdrawals` | Status badges, pull-to-refresh |
| **Profile** | `GET /profile`, `PUT /profile` | Edit form |
| **Vehicle** | `PUT /profile/vehicle` | Vehicle type, category, plate |
| **Change Password** | `PUT /profile/password` | Current + new password form |

### Recommended Flutter Packages

| Purpose | Package |
|---------|---------|
| HTTP Client | `dio` or `http` |
| State Management | `riverpod` or `bloc` |
| Local Storage | `shared_preferences` or `flutter_secure_storage` (for token) |
| Push Notifications | `firebase_messaging` |
| Maps | `google_maps_flutter` |
| Location | `geolocator` + `location` |
| Background Location | `flutter_background_service` |
| WebSocket | `laravel_echo` (Dart) or `pusher_client` |
| Image Picker | `image_picker` |
| Navigation | `go_router` |

---

## Appendix: Complete Endpoint Reference

| Method | Endpoint | Auth | Approved | Description |
|--------|----------|------|----------|-------------|
| POST | `/auth/register` | No | No | Register rider |
| POST | `/auth/login` | No | No | Login |
| POST | `/auth/otp/send` | No | No | Send OTP |
| POST | `/auth/otp/verify` | No | No | Verify OTP |
| POST | `/auth/forgot-password` | No | No | Request password reset |
| POST | `/auth/reset-password` | No | No | Reset password |
| POST | `/auth/logout` | Yes | No | Logout |
| POST | `/onboarding/documents` | Yes | No | Submit documents |
| GET | `/onboarding/status` | Yes | No | Check status |
| PUT | `/onboarding/documents` | Yes | No | Resubmit documents |
| GET | `/dashboard` | Yes | Yes | Dashboard stats |
| POST | `/dashboard/toggle-online` | Yes | Yes | Toggle availability |
| POST | `/dashboard/location` | Yes | Yes | Update GPS |
| PUT | `/dashboard/device-token` | Yes | Yes | Update FCM token |
| GET | `/deliveries/incoming` | Yes | Yes | Incoming requests |
| GET | `/deliveries/active` | Yes | Yes | Current delivery |
| GET | `/deliveries/history` | Yes | Yes | Past deliveries |
| GET | `/deliveries/{id}` | Yes | Yes | Delivery detail |
| POST | `/deliveries/{id}/accept` | Yes | Yes | Accept delivery |
| POST | `/deliveries/{id}/decline` | Yes | Yes | Decline delivery |
| POST | `/deliveries/{id}/pickup` | Yes | Yes | Confirm pickup |
| POST | `/deliveries/{id}/deliver` | Yes | Yes | Confirm delivery (PIN) |
| POST | `/deliveries/{id}/cancel` | Yes | Yes | Cancel delivery |
| GET | `/earnings` | Yes | Yes | Balance summary |
| GET | `/earnings/transactions` | Yes | Yes | Transaction history |
| POST | `/earnings/withdraw` | Yes | Yes | Request withdrawal |
| GET | `/earnings/withdrawals` | Yes | Yes | Withdrawal history |
| GET | `/profile` | Yes | Yes | Get profile |
| PUT | `/profile` | Yes | Yes | Update profile |
| PUT | `/profile/vehicle` | Yes | Yes | Update vehicle |
| PUT | `/profile/password` | Yes | Yes | Change password |
