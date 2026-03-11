# Rider App API — Design Document

**Date:** 2026-03-10
**Status:** Approved
**Stakeholders:** Board of Directors, Backend Team, Flutter Team

## Overview

A rider/delivery app for the Surprise Moi platform, similar to Uber/Bolt but tailored for gift and package delivery in Ghana. Riders pick up orders from vendors and deliver them to customers.

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Rider onboarding | Self-registration + admin approval | Consistent with vendor onboarding pattern |
| Vehicle types | Motorbikes + Cars | Covers small packages and larger/fragile items |
| Order assignment | Vendor-preferred riders + open broadcast | Vendors have existing rider relationships |
| Open order broadcast | Push to nearby riders, expanding radius | Industry standard (Uber/Bolt model) |
| Rider payments | Per-delivery balance + withdrawal | Reuses vendor payout system patterns |
| API structure | Separate module (`/api/rider/v1/`) | Own route file + controllers, shared models/services |

## API Structure

```
routes/api_rider.php                          # Dedicated route file
App\Http\Controllers\Api\Rider\V1\           # Rider-specific controllers
App\Http\Requests\Api\Rider\V1\              # Rider form requests
App\Http\Resources\Api\Rider\V1\             # Rider API resources
App\Services\RiderService.php                 # Rider business logic
App\Services\DeliveryDispatchService.php      # Broadcast & assignment logic
```

- **Prefix:** `/api/rider/v1/`
- **Auth:** Laravel Sanctum (token-based), same as existing API
- **Shared:** Models, Middleware, Notifications, Events from existing codebase

## Database Schema

### Extend `riders` table

New columns added to the existing `riders` table:

| Column | Type | Description |
|--------|------|-------------|
| `password` | string | Hashed password for auth |
| `email_verified_at` | timestamp, nullable | Email verification |
| `phone` | string | Phone number (already exists) |
| `phone_verified_at` | timestamp, nullable | Phone OTP verification |
| `ghana_card_front` | string, nullable | Ghana card front image path |
| `ghana_card_back` | string, nullable | Ghana card back image path |
| `drivers_license` | string, nullable | Driver's license image path |
| `vehicle_photo` | string, nullable | Vehicle photo path |
| `vehicle_category` | enum: motorbike, car | Type of vehicle |
| `status` | enum: pending, under_review, approved, rejected, suspended | Approval status |
| `is_online` | boolean, default false | Availability toggle |
| `current_latitude` | decimal(10,7), nullable | Live GPS latitude |
| `current_longitude` | decimal(10,7), nullable | Live GPS longitude |
| `location_updated_at` | timestamp, nullable | Last GPS update |
| `device_token` | string, nullable | FCM push notification token |
| `average_rating` | decimal(3,2), default 0 | Average delivery rating |
| `total_deliveries` | integer, default 0 | Completed delivery count |

### New table: `delivery_requests`

Tracks the lifecycle of a delivery from broadcast to completion.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `order_id` | foreignId | References orders table |
| `rider_id` | foreignId, nullable | Assigned rider (null during broadcast) |
| `vendor_id` | foreignId | The vendor creating the request |
| `assigned_rider_id` | foreignId, nullable | Vendor's preferred rider (if any) |
| `status` | enum | broadcasting, assigned, accepted, picked_up, in_transit, delivered, cancelled, expired |
| `pickup_address` | string | Vendor/pickup address |
| `pickup_latitude` | decimal(10,7) | Pickup GPS lat |
| `pickup_longitude` | decimal(10,7) | Pickup GPS lng |
| `dropoff_address` | string | Customer/delivery address |
| `dropoff_latitude` | decimal(10,7) | Dropoff GPS lat |
| `dropoff_longitude` | decimal(10,7) | Dropoff GPS lng |
| `delivery_fee` | decimal(10,2) | Fee rider earns |
| `distance_km` | decimal(8,2), nullable | Estimated distance |
| `broadcast_radius_km` | decimal(5,2), default 5 | Current broadcast radius |
| `broadcast_attempts` | integer, default 0 | Number of broadcast rounds |
| `accepted_at` | timestamp, nullable | When rider accepted |
| `picked_up_at` | timestamp, nullable | When rider picked up package |
| `delivered_at` | timestamp, nullable | When delivery confirmed |
| `expires_at` | timestamp, nullable | Current broadcast expiry |
| `cancellation_reason` | string, nullable | If cancelled, why |
| `timestamps` | | created_at, updated_at |
| `soft_deletes` | | deleted_at |

### New table: `rider_earnings`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `rider_id` | foreignId | References riders table |
| `order_id` | foreignId | References orders table |
| `delivery_request_id` | foreignId | References delivery_requests |
| `amount` | decimal(10,2) | Earning amount (GHS) |
| `type` | enum: delivery_fee, bonus, adjustment | Type of earning |
| `status` | enum: pending, available, withdrawn | Pending = delivery hold period, available = can withdraw |
| `available_at` | timestamp | When funds become available (24h after delivery) |
| `timestamps` | | created_at, updated_at |

### New table: `rider_withdrawal_requests`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `rider_id` | foreignId | References riders table |
| `amount` | decimal(10,2) | Withdrawal amount (GHS) |
| `status` | enum: pending, processing, completed, rejected, failed | Withdrawal status |
| `mobile_money_provider` | enum: mtn, vodafone, airteltigo | MoMo provider |
| `mobile_money_number` | string | MoMo number |
| `processed_at` | timestamp, nullable | When admin processed |
| `rejection_reason` | string, nullable | If rejected, why |
| `timestamps` | | created_at, updated_at |

### New table: `vendor_riders` (pivot)

Links vendors to their preferred riders.

| Column | Type | Description |
|--------|------|-------------|
| `id` | id | Primary key |
| `vendor_id` | foreignId | References users table |
| `rider_id` | foreignId | References riders table |
| `nickname` | string, nullable | Vendor's name for this rider |
| `is_default` | boolean, default false | Default rider for this vendor |
| `timestamps` | | created_at, updated_at |

### New table: `rider_location_logs`

For delivery tracking history (optional, for route replay).

| Column | Type | Description |
|--------|------|-------------|
| `id` | id | Primary key |
| `rider_id` | foreignId | References riders |
| `delivery_request_id` | foreignId | Active delivery |
| `latitude` | decimal(10,7) | GPS lat |
| `longitude` | decimal(10,7) | GPS lng |
| `recorded_at` | timestamp | When recorded |

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new rider |
| POST | `/auth/login` | Login with email/phone + password |
| POST | `/auth/otp/send` | Send OTP to phone |
| POST | `/auth/otp/verify` | Verify phone OTP |
| POST | `/auth/forgot-password` | Request password reset |
| POST | `/auth/reset-password` | Reset password |
| POST | `/auth/logout` | Revoke token |

### Onboarding (authenticated, pre-approval)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/onboarding/documents` | Upload Ghana card, license, vehicle photo |
| GET | `/onboarding/status` | Check approval status |
| PUT | `/onboarding/documents` | Re-upload if rejected |

### Dashboard (authenticated, approved riders only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Stats: today's earnings, total deliveries, rating |
| POST | `/dashboard/toggle-online` | Go online/offline |
| POST | `/dashboard/location` | Update GPS coordinates |
| PUT | `/dashboard/device-token` | Update FCM token |

### Deliveries

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/deliveries/incoming` | Get pending delivery requests for this rider |
| POST | `/deliveries/{delivery_request}/accept` | Accept a delivery |
| POST | `/deliveries/{delivery_request}/decline` | Decline a delivery |
| GET | `/deliveries/active` | Get current active delivery |
| POST | `/deliveries/{delivery_request}/pickup` | Confirm package picked up |
| POST | `/deliveries/{delivery_request}/deliver` | Confirm delivery with PIN |
| POST | `/deliveries/{delivery_request}/cancel` | Cancel (with reason) |
| GET | `/deliveries/history` | Past deliveries (paginated) |
| GET | `/deliveries/{delivery_request}` | Delivery detail |

### Earnings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/earnings` | Balance summary (available, pending, total) |
| GET | `/earnings/transactions` | Transaction history (paginated) |
| POST | `/earnings/withdraw` | Request withdrawal |
| GET | `/earnings/withdrawals` | Withdrawal request history |

### Profile

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profile` | Get rider profile |
| PUT | `/profile` | Update personal info |
| PUT | `/profile/vehicle` | Update vehicle details |
| PUT | `/profile/password` | Change password |

## Delivery Assignment Flow

```
Vendor creates order with delivery_method = "platform_rider"
                    ↓
    ┌───────────────────────────────┐
    │ Vendor has preferred rider?    │
    ├── YES ────────────────────────┤
    │   Create delivery_request     │
    │   with assigned_rider_id      │
    │   Send FCM to that rider      │
    │   30s to accept               │
    │   ├── Accepted → Lock it      │
    │   └── Declined/Timeout        │
    │       → Fall to open broadcast│
    ├── NO ─────────────────────────┤
    │   Go directly to broadcast    │
    └───────────────────────────────┘
                    ↓
         Open Broadcast Flow
    ┌───────────────────────────────┐
    │ Attempt 1: 5km radius, 30s   │
    │ ├── Rider accepts → Done     │
    │ └── No accept                │
    │                              │
    │ Attempt 2: 10km radius, 30s  │
    │ ├── Rider accepts → Done     │
    │ └── No accept                │
    │                              │
    │ Attempt 3: 20km radius, 30s  │
    │ ├── Rider accepts → Done     │
    │ └── No accept                │
    │                              │
    │ All failed → Notify vendor   │
    │ Suggest self-delivery or     │
    │ third-party courier          │
    └───────────────────────────────┘
```

## Active Delivery Flow

```
ACCEPTED → Rider navigates to vendor
    ↓
PICKED_UP → Rider confirms pickup at vendor location
    ↓
IN_TRANSIT → Rider navigates to customer (live tracking)
    ↓
DELIVERED → Customer provides 4-digit PIN → Delivery confirmed
    ↓
Earnings credited (available after 24h hold)
Order status updated to "delivered"
```

## Real-time Features

| Feature | Technology | Description |
|---------|-----------|-------------|
| Delivery requests | FCM Push | Notify riders of new deliveries |
| Live tracking | Laravel Reverb (WebSocket) | Customer sees rider location during delivery |
| Location updates | REST API (periodic) | Rider app sends GPS every 10-15 seconds during active delivery |
| Status changes | FCM + Reverb | Notify customer/vendor of pickup, delivery, etc. |

## Vendor Integration (Surprise Moi App)

Vendors need these new capabilities in the existing `/api/v1/` API:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/vendor/riders` | List vendor's preferred riders |
| POST | `/vendor/riders` | Add a preferred rider |
| DELETE | `/vendor/riders/{rider}` | Remove a preferred rider |
| POST | `/vendor/orders/{order}/dispatch` | Create delivery request (assign rider or open) |
| GET | `/vendor/orders/{order}/delivery-status` | Track delivery progress |

## Security Considerations

- Rider tokens scoped with `rider` ability to prevent cross-role access
- Document uploads validated (file type, size)
- Location updates rate-limited
- Delivery PIN prevents unauthorized delivery confirmation
- Withdrawal requests require admin approval
