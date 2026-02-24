# Referral & Target Systems

This document covers the referral program, target tracking, and earnings system for influencers, field agents, and marketers.

## Overview

The platform has a comprehensive multi-tier marketing system with three specialized roles:

- **Influencers** - Generate referral codes, earn commissions on vendor onboarding and sales
- **Field Agents** - Complete location-based targets, earn fixed rewards
- **Marketers** - Manage regional campaigns, earn quarterly bonuses

## Core Models

### ReferralCode

**Location**: `app/Models/ReferralCode.php`

Unique codes created by influencers to track referrals.

```php
[
    'user_id',           // Influencer who owns the code
    'code',              // Unique code (e.g., 'GIFT2024')
    'discount_type',     // 'percentage' or 'fixed'
    'discount_value',    // Discount amount
    'currency',
    'usage_limit',       // Total uses allowed (null = unlimited)
    'usage_count',       // Current usage count
    'is_active',         // Active status
    'valid_from',
    'valid_until',
    'description',       // Optional description
]
```

**Usage**: Applied during vendor onboarding to give discounts and track referrals.

### Referral

**Location**: `app/Models/Referral.php`

Tracks each time a referral code is used.

```php
[
    'referral_code_id',  // The code that was used
    'influencer_id',     // Owner of the code
    'vendor_id',         // Vendor who was referred
    'status',            // 'pending', 'active', 'completed', 'cancelled'
    'commission_rate',   // % commission (set when referral created)
    'commission_period', // How long commission lasts (days)
    'expires_at',        // When commission period ends
    'total_sales',       // Cumulative sales from this vendor
    'total_commission',  // Total commission earned
]
```

**Commission Lifecycle**:

1. Vendor signs up with code → Referral created (status: pending)
2. Vendor application approved → Referral activated (status: active)
3. Vendor makes sales → Commission calculated
4. Commission period ends → Referral completed (status: completed)

### Earning

**Location**: `app/Models/Earning.php`

Individual earning records for all commission types.

```php
[
    'user_id',           // Influencer/field_agent/marketer
    'order_id',          // Related order (for sales commission)
    'referral_id',       // Related referral (for influencer)
    'target_id',         // Related target (for field agents)
    'type',              // 'onboarding', 'commission', 'target', 'bonus'
    'amount',            // Earning amount
    'currency',
    'status',            // 'pending', 'approved', 'paid'
    'description',
    'paid_at',
]
```

### Target

**Location**: `app/Models/Target.php`

Goals assigned to field agents and marketers.

```php
[
    'name',              // Target name
    'description',
    'type',              // 'vendor_registration', 'sales_volume', 'customer_acquisition'
    'target_type',       // 'field_agent' or 'marketer'
    'target_value',      // Goal number
    'reward_amount',     // Payment for completing target
    'currency',
    'period_start',
    'period_end',
    'is_active',
]
```

### TargetAchievement

**Location**: `app/Models/TargetAchievement.php`

Tracks progress toward targets.

```php
[
    'target_id',
    'user_id',           // Field agent or marketer
    'current_value',     // Current progress
    'is_completed',
    'completed_at',
    'reward_paid',
]
```

## Influencer System

### Creating Referral Codes

**Endpoint**: `POST /api/v1/referral-codes`

**Authorization**: Role 'influencer'

**Request**:

```json
{
    "code": "GIFT2024",
    "discount_type": "percentage",
    "discount_value": 10,
    "description": "10% off vendor onboarding",
    "valid_from": "2026-02-01",
    "valid_until": "2026-12-31",
    "usage_limit": 100
}
```

**Validation**:

- Code must be unique
- Must be alphanumeric (no spaces/special chars)
- Discount value must be reasonable (0-50% or fixed amount)

### Listing Referral Codes

**Endpoint**: `GET /api/v1/referral-codes`

Returns influencer's codes with usage statistics.

**Response**:

```json
{
    "data": [
        {
            "id": 1,
            "code": "GIFT2024",
            "discount_type": "percentage",
            "discount_value": 10,
            "usage_count": 15,
            "usage_limit": 100,
            "is_active": true,
            "referrals_count": 15,
            "active_referrals": 12,
            "total_commission_earned": 450.0
        }
    ]
}
```

### Viewing Referral Performance

**Endpoint**: `GET /api/v1/influencer/referrals`

Detailed list of all referrals.

**Response**:

```json
{
    "data": [
        {
            "id": 5,
            "vendor": {
                "id": 25,
                "name": "Gift Paradise",
                "email": "vendor@example.com"
            },
            "status": "active",
            "commission_rate": 5.0,
            "commission_period": 180,
            "expires_at": "2026-08-02",
            "total_sales": 2340.0,
            "total_commission": 117.0
        }
    ]
}
```

### Commission Calculation

**Service**: `app/Services/ReferralService.php`

When a vendor (who was referred) makes a sale:

```php
public function calculateCommission(Referral $referral, float $orderTotal): void
{
    // Check if referral is still active
    if ($referral->status !== 'active') {
        return;
    }

    // Check if commission period expired
    if ($referral->expires_at && $referral->expires_at->isPast()) {
        $referral->update(['status' => 'completed']);
        return;
    }

    // Calculate commission
    $commissionAmount = $orderTotal * ($referral->commission_rate / 100);

    // Update referral totals
    $referral->increment('total_sales', $orderTotal);
    $referral->increment('total_commission', $commissionAmount);

    // Create earning record
    Earning::create([
        'user_id' => $referral->influencer_id,
        'referral_id' => $referral->id,
        'order_id' => $order->id,
        'type' => 'commission',
        'amount' => $commissionAmount,
        'currency' => config('app.currency'),
        'status' => 'pending',
        'description' => "Commission from vendor order #{$order->order_number}",
    ]);
}
```

**Trigger Point**: In `PaystackService::processVerificationResponse()` after successful payment verification.

### Influencer Dashboard

**Endpoint**: `GET /api/v1/influencer/dashboard`

**Response**:

```json
{
    "overview": {
        "total_referrals": 45,
        "active_referrals": 32,
        "total_earnings": 2340.5,
        "pending_earnings": 450.0,
        "this_month_earnings": 890.0
    },
    "top_performing_codes": [
        {
            "code": "GIFT2024",
            "referrals": 15,
            "earnings": 670.0
        }
    ],
    "recent_referrals": [
        /* ... */
    ]
}
```

### Influencer Earnings

**Endpoint**: `GET /api/v1/influencer/earnings`

**Query Parameters**:

- `status` - Filter by pending/approved/paid
- `start_date`, `end_date` - Date range

**Response**:

```json
{
    "data": [
        {
            "id": 123,
            "type": "commission",
            "amount": 23.4,
            "status": "approved",
            "description": "Commission from vendor order #ORD-ABC123",
            "created_at": "2026-02-03T10:00:00Z"
        }
    ],
    "summary": {
        "total_pending": 450.0,
        "total_approved": 1890.5,
        "total_paid": 2340.5
    }
}
```

## Field Agent System

### Viewing Assigned Targets

**Endpoint**: `GET /api/v1/field-agent/targets`

Returns targets assigned to the field agent.

**Response**:

```json
{
    "data": [
        {
            "id": 5,
            "name": "Register 10 vendors in Accra",
            "description": "Sign up vendors in Accra region",
            "type": "vendor_registration",
            "target_value": 10,
            "current_value": 7,
            "progress_percentage": 70,
            "reward_amount": 500.0,
            "period_start": "2026-02-01",
            "period_end": "2026-02-28",
            "is_completed": false
        }
    ]
}
```

### Recording Achievement

Field agents record progress through the system (admins verify):

**Process**:

1. Field agent reports activity (e.g., vendor registration)
2. System tracks `TargetAchievement.current_value`
3. When `current_value >= target_value`:
    - Mark as completed
    - Create earning record
    - Notify field agent

```php
// In TargetService
public function recordProgress(Target $target, User $fieldAgent, int $increment = 1): void
{
    $achievement = TargetAchievement::firstOrCreate([
        'target_id' => $target->id,
        'user_id' => $fieldAgent->id,
    ], [
        'current_value' => 0,
    ]);

    $achievement->increment('current_value', $increment);

    // Check if target is met
    if ($achievement->current_value >= $target->target_value && !$achievement->is_completed) {
        $achievement->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Create earning
        Earning::create([
            'user_id' => $fieldAgent->id,
            'target_id' => $target->id,
            'type' => 'target',
            'amount' => $target->reward_amount,
            'status' => 'approved',
            'description' => "Target completed: {$target->name}",
        ]);

        // Notify field agent
        $fieldAgent->notify(new TargetCompleted($achievement));
    }
}
```

### Field Agent Dashboard

**Endpoint**: `GET /api/v1/field-agent/dashboard`

**Response**:

```json
{
    "overview": {
        "active_targets": 3,
        "completed_targets": 12,
        "total_earnings": 4500.0,
        "pending_earnings": 500.0
    },
    "current_targets": [
        /* ... */
    ],
    "earnings_this_month": 1000.0
}
```

## Marketer System

Marketers manage regional campaigns and earn quarterly bonuses based on overall performance.

### Marketer Dashboard

**Endpoint**: `GET /api/v1/marketer/dashboard`

**Response**:

```json
{
    "overview": {
        "region": "Greater Accra",
        "quarterly_target": 50000.0,
        "current_sales": 42300.0,
        "progress_percentage": 84.6,
        "estimated_bonus": 5000.0
    },
    "regional_stats": {
        "total_vendors": 145,
        "active_vendors": 132,
        "total_customers": 2340,
        "orders_this_quarter": 567
    }
}
```

### Quarterly Earnings

**Endpoint**: `GET /api/v1/marketer/quarterly-earnings`

Shows earnings breakdown by quarter.

## Payout Requests

All roles (influencer, field_agent, marketer) can request payouts.

### Create Payout Request

**Endpoint**: `POST /api/v1/payout-requests`

**Authorization**: Roles 'influencer', 'field_agent', 'marketer'

**Request**:

```json
{
    "amount": 500.0,
    "payment_method": "mobile_money",
    "account_details": {
        "phone": "+233123456789",
        "network": "MTN",
        "account_name": "John Doe"
    }
}
```

**Validation**:

- Amount must not exceed approved earnings
- Minimum payout: e.g., $10

### List Payout Requests

**Endpoint**: `GET /api/v1/payout-requests`

Returns user's payout history.

**Response**:

```json
{
    "data": [
        {
            "id": 10,
            "amount": 500.0,
            "status": "approved",
            "payment_method": "mobile_money",
            "processed_at": "2026-02-01T10:00:00Z"
        }
    ]
}
```

### Admin: Process Payouts

**Endpoint**: `POST /api/v1/admin/payout-requests/{payoutRequest}/approve`

Admin approves and processes payout via Paystack transfer.

## Admin: Target Management

**Controller**: `app/Http/Controllers/TargetController.php`

### Create Target

**Endpoint**: `POST /api/v1/admin/targets`

**Request**:

```json
{
    "name": "Q1 Vendor Registrations - Accra",
    "description": "Register 20 vendors in Accra",
    "type": "vendor_registration",
    "target_type": "field_agent",
    "target_value": 20,
    "reward_amount": 1000.0,
    "period_start": "2026-02-01",
    "period_end": "2026-04-30"
}
```

### Assign Target to Users

Targets can be assigned to specific field agents or marketers, or made available to all.

### Monitor Progress

**Endpoint**: `GET /api/v1/admin/targets/{target}`

Shows all users working on the target and their progress.

## EarningService

**Location**: `app/Services/EarningService.php`

Centralizes earning calculations and status updates.

```php
// Approve earnings
public function approveEarnings(array $earningIds): void
{
    Earning::whereIn('id', $earningIds)
        ->where('status', 'pending')
        ->update(['status' => 'approved']);
}

// Mark as paid
public function markAsPaid(array $earningIds): void
{
    Earning::whereIn('id', $earningIds)
        ->where('status', 'approved')
        ->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
}

// Calculate total pending for user
public function getPendingTotal(User $user): float
{
    return Earning::where('user_id', $user->id)
        ->where('status', 'pending')
        ->sum('amount');
}
```

## Testing

```php
public function test_influencer_earns_commission_from_referral(): void
{
    $influencer = User::factory()->create(['role' => 'influencer']);
    $referralCode = ReferralCode::factory()->create(['user_id' => $influencer->id]);

    // Vendor signs up with code
    $vendor = User::factory()->create();
    $referral = Referral::create([
        'referral_code_id' => $referralCode->id,
        'influencer_id' => $influencer->id,
        'vendor_id' => $vendor->id,
        'status' => 'active',
        'commission_rate' => 5.0,
        'commission_period' => 180,
    ]);

    // Vendor makes a sale
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'total' => 100.00,
        'payment_status' => 'paid',
    ]);

    // Calculate commission
    $this->referralService->calculateCommission($referral, $order->total);

    // Assert commission recorded
    $this->assertDatabaseHas('earnings', [
        'user_id' => $influencer->id,
        'referral_id' => $referral->id,
        'amount' => 5.00, // 5% of 100
        'type' => 'commission',
    ]);
}
```

---

This multi-tier referral and target system incentivizes growth across multiple channels while maintaining clear tracking and fair compensation.
