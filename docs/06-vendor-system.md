# Vendor System & Onboarding

This document covers the vendor registration process, application management, and vendor-specific features.

## Overview

The vendor onboarding system is a multi-step wizard that collects necessary documentation and verifies vendors before granting access to sell on the platform. The process includes:

1. Ghana Card verification
2. Business registration status
3. Document upload (varies by business type)
4. Bespoke services selection
5. Payment of onboarding fee
6. Admin review and approval

## VendorApplication Model

**Location**: `app/Models/VendorApplication.php`

### Status Constants

```php
const STATUS_PENDING = 'pending';         // In progress, not submitted
const STATUS_UNDER_REVIEW = 'under_review'; // Submitted, awaiting admin review
const STATUS_APPROVED = 'approved';       // Approved, user becomes vendor
const STATUS_REJECTED = 'rejected';       // Rejected, can reapply
```

### Attributes

```php
[
    'user_id',
    'status',
    'current_step',                  // Step currently working on
    'completed_step',                // Last completed step

    // Step 1: Ghana Card
    'ghana_card_front',              // Image path
    'ghana_card_back',

    // Step 2: Business Registration
    'has_business_certificate',      // Boolean flag

    // Step 3A: Registered Business Documents
    'business_certificate_document', // For registered businesses

    // Step 3B: Unregistered Vendor Documents
    'selfie_image',                  // For identity verification
    'mobile_money_number',           // For verification
    'mobile_money_provider',         // 'mtn', 'vodafone', 'airteltigo'
    'proof_of_business',             // Shop photos, etc.

    // Step 3: Social Media (Both paths)
    'facebook_handle',
    'instagram_handle',
    'twitter_handle',

    // Payment (Step 5)
    'payment_required',              // Boolean
    'payment_completed',             // Boolean
    'payment_completed_at',
    'coupon_id',                     // Discount coupon
    'onboarding_fee',                // Base fee
    'discount_amount',               // Coupon discount
    'final_amount',                  // After discount

    // Admin Review
    'rejection_reason',
    'reviewed_by',                   // Admin user ID
    'reviewed_at',
    'submitted_at',
]
```

### Relationships

```php
$application->user()              // BelongsTo User
$application->reviewer()          // BelongsTo User (admin)
$application->bespokeServices()   // BelongsToMany BespokeService
$application->coupon()            // BelongsTo Coupon (referral discount)
$application->payment()           // HasOne VendorOnboardingPayment
```

### Helper Methods

```php
// Check if application can be edited
$application->isEditable(): bool
// Returns true if status is 'pending' and not submitted

// Check if ready for submission
$application->canBeSubmitted(): bool
// Returns true if all required steps completed and payment done
```

## Registration Flow

**Controller**: `app/Http/Controllers/Api/V1/VendorRegistrationController.php`

All endpoints require authentication.

### Check Status

`GET /api/v1/vendor-registration/status`

Returns current application state.

**Response** (No Application):

```json
{
    "success": true,
    "data": {
        "has_application": false,
        "can_start_new": true,
        "message": "No vendor application found..."
    }
}
```

**Response** (In Progress):

```json
{
    "success": true,
    "data": {
        "has_application": true,
        "is_submitted": false,
        "is_editable": true,
        "can_start_new": false,
        "application": {
            "id": 5,
            "status": "pending",
            "current_step": 2,
            "completed_step": 1,
            "has_business_certificate": null
            // ... other fields
        },
        "message": "Continue your application from Step 2."
    }
}
```

### Step 1: Upload Ghana Card

`POST /api/v1/vendor-registration/step-1/ghana-card`

**Request**: Multipart form data

- `ghana_card_front` (file, required, image, max 2MB)
- `ghana_card_back` (file, required, image, max 2MB)

**Process**:

1. Check for existing active application
2. Create or update pending application
3. Store images in `storage/app/public/vendor-applications/ghana-cards/`
4. Update `current_step` and `completed_step` to 1

**Response**:

```json
{
    "success": true,
    "message": "Ghana Card uploaded successfully. Proceed to Step 2.",
    "data": {
        "application": {
            /* Application object */
        },
        "next_step": 2
    }
}
```

### Step 2: Business Registration Status

`POST /api/v1/vendor-registration/step-2/business-registration`

**Request**:

```json
{
    "has_business_certificate": true
}
```

**Validation**:

- Must have completed Step 1
- `has_business_certificate` must be boolean

**Process**:

- Updates `has_business_certificate` flag
- Determines next step path:
    - `true` → Step 3A (registered vendor)
    - `false` → Step 3B (unregistered vendor)

**Response**:

```json
{
    "success": true,
    "message": "Business registration status saved.",
    "data": {
        "application": {
            /* ... */
        },
        "next_step": 3,
        "path": "registered"
    }
}
```

### Step 3A: Registered Vendor Documents

`POST /api/v1/vendor-registration/step-3/registered-documents`

For vendors with business certificates.

**Request**: Multipart form data

- `business_certificate_document` (file, required, pdf/image, max 5MB)
- `facebook_handle` (optional, string)
- `instagram_handle` (optional, string)
- `twitter_handle` (optional, string)

**Process**:

1. Validate Step 2 completed with `has_business_certificate = true`
2. Store certificate document
3. Save social media handles
4. Update to step 3

### Step 3B: Unregistered Vendor Documents

`POST /api/v1/vendor-registration/step-3/unregistered-documents`

For vendors without business registration.

**Request**: Multipart form data

- `selfie_image` (file, required, image, max 2MB)
- `mobile_money_number` (required, Ghana phone number)
- `mobile_money_provider` (required, 'mtn'|'vodafone'|'airteltigo')
- `proof_of_business` (file, required, image, max 5MB) - Shop photos, etc.
- `facebook_handle` (optional)
- `instagram_handle` (optional)
- `twitter_handle` (optional)

**Validation**:

- Phone number format: `+233XXXXXXXXX`
- Selfie must show clear face
- Proof of business (shop photos, product images, etc.)

### Step 4: Select Bespoke Services

`POST /api/v1/vendor-registration/step-4/bespoke-services`

Select optional value-added services.

**Request**:

```json
{
    "bespoke_service_ids": [1, 3, 5]
}
```

**Bespoke Services** (from `bespoke_services` table):

- Gift wrapping service
- Personalized greeting cards
- Express delivery
- Same-day delivery
- Gift registry management
- Surprise planning consultation

**Get Available Services**:
`GET /api/v1/vendor-registration/bespoke-services`

Returns active services with details.

**Process**:

1. Validate service IDs exist
2. Attach services to application
3. Update to step 4

### Step 5: Payment

Payment is required before final submission.

#### Get Payment Summary

`GET /api/v1/vendor-registration/payment/summary`

**Response**:

```json
{
    "success": true,
    "data": {
        "onboarding_fee": 100.0,
        "discount_amount": 10.0,
        "final_amount": 90.0,
        "currency": "GHS",
        "coupon_applied": {
            "code": "INFLUENCER10",
            "discount": "10%"
        }
    }
}
```

#### Validate Coupon

`POST /api/v1/vendor-registration/payment/validate-coupon`

**Request**:

```json
{
    "coupon_code": "INFLUENCER10"
}
```

**Response**:

```json
{
    "valid": true,
    "discount_amount": 10.0,
    "final_amount": 90.0,
    "coupon": {
        "code": "INFLUENCER10",
        "discount_type": "percentage",
        "discount_value": 10
    }
}
```

#### Initiate Payment

`POST /api/v1/vendor-registration/payment/initiate`

**Request**:

```json
{
    "coupon_code": "INFLUENCER10",
    "callback_url": "https://app.example.com/vendor/payment-callback"
}
```

**Process**:

1. Calculate final amount with discount
2. Initialize Paystack transaction
3. Create `VendorOnboardingPayment` record
4. Return Paystack authorization URL

**Response**:

```json
{
    "success": true,
    "data": {
        "authorization_url": "https://checkout.paystack.com/...",
        "reference": "VONB-XXXXXXXXXXXXXXXX",
        "amount": 90.0
    }
}
```

#### Verify Payment

`POST /api/v1/vendor-registration/payment/verify`

**Request**:

```json
{
    "reference": "VONB-XXXXXXXXXXXXXXXX"
}
```

**Process**:

1. Verify with Paystack
2. Update application payment status
3. Mark application as ready for submission

**Response**:

```json
{
    "success": true,
    "message": "Payment verified successfully.",
    "data": {
        "payment_completed": true,
        "can_submit": true
    }
}
```

### Step 6: Submit Application

`POST /api/v1/vendor-registration/submit`

Final submission for admin review.

**Validation**:

- All steps completed
- Payment verified
- Application status is 'pending'

**Process**:

1. Validate all required fields
2. Update status to 'under_review'
3. Set `submitted_at` timestamp
4. Notify admins (optional)

**Response**:

```json
{
    "success": true,
    "message": "Application submitted successfully!",
    "data": {
        "application": {
            "id": 5,
            "status": "under_review",
            "submitted_at": "2026-02-03T14:30:00Z"
        }
    }
}
```

## Admin Review

**Controller**: `app/Http/Controllers/VendorApplicationController.php` (Web Dashboard)

Admins review applications through the web dashboard.

### List Applications

`GET /dashboard/vendor-applications`

Displays paginated list with filters:

- Status filter (pending, under_review, approved, rejected)
- Search by user name/email
- Date range

### View Application

`GET /dashboard/vendor-applications/{application}`

Shows complete application details:

- User information
- All uploaded documents
- Selected bespoke services
- Payment status
- Timeline of actions

### Approve Application

`POST /dashboard/vendor-applications/{application}/approve`

**Effect**:

1. Update status to 'approved'
2. Update user's role to 'vendor'
3. Set `reviewed_by` and `reviewed_at`
4. Send approval email to user
5. Create VendorBalance record

```php
public function approve(VendorApplication $application): RedirectResponse
{
    if ($application->status !== VendorApplication::STATUS_UNDER_REVIEW) {
        return back()->withErrors(['message' => 'Application not under review']);
    }

    DB::transaction(function () use ($application) {
        // Approve application
        $application->update([
            'status' => VendorApplication::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Upgrade user to vendor
        $application->user->update(['role' => 'vendor']);

        // Initialize vendor balance
        VendorBalance::create([
            'vendor_id' => $application->user_id,
            'pending_balance' => 0,
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'currency' => config('app.currency', 'GHS'),
        ]);

        // Send approval notification
        $application->user->notify(new VendorApplicationApproved($application));
    });

    return redirect()->route('vendor-applications.index')
        ->with('success', 'Application approved successfully');
}
```

### Reject Application

`POST /dashboard/vendor-applications/{application}/reject`

**Request**:

```json
{
    "rejection_reason": "Documents are not clear. Please resubmit with better quality images."
}
```

**Effect**:

1. Update status to 'rejected'
2. Set rejection reason
3. Send rejection email with reason
4. User can start new application

### Mark Under Review

`POST /dashboard/vendor-applications/{application}/under-review`

Changes status from 'pending' to 'under_review' when admin starts reviewing.

## Vendor Analytics

**Controller**: `app/Http/Controllers/Api/V1/VendorAnalyticsController.php`

Analytics for vendors to track performance.

### Overview

`GET /api/v1/vendor/analytics/overview`

**Query Parameters**:

- `start_date`, `end_date` - Date range (defaults to last 30 days)

**Response**:

```json
{
    "total_revenue": 5430.5,
    "total_orders": 145,
    "completed_orders": 132,
    "pending_orders": 8,
    "average_order_value": 37.45,
    "total_products": 45,
    "total_services": 8,
    "new_customers": 23,
    "customer_retention_rate": 65.5
}
```

### Revenue by Category

`GET /api/v1/vendor/analytics/revenue-by-category`

Breakdown of revenue by product categories.

### Top Products

`GET /api/v1/vendor/analytics/top-products`

**Query Parameters**:

- `limit` - Number of products (default: 10)
- `sort_by` - 'revenue' or 'quantity' (default: revenue)

### Trends

`GET /api/v1/vendor/analytics/trends`

Daily/weekly/monthly sales trends for charts.

**Query Parameters**:

- `period` - 'daily', 'weekly', 'monthly'
- `start_date`, `end_date`

## VendorOnboardingPayment Model

**Location**: `app/Models/VendorOnboardingPayment.php`

Tracks onboarding fee payments separately from order payments.

```php
[
    'vendor_application_id',
    'user_id',
    'reference',               // VONB-XXXXXXXXXXXXXXXX
    'paystack_reference',
    'authorization_url',
    'amount',
    'discount_amount',
    'final_amount',
    'currency',
    'status',                  // pending, success, failed
    'channel',                 // card, bank, mobile_money
    'paid_at',
]
```

## Testing Vendor Registration

```php
public function test_complete_vendor_registration_flow(): void
{
    Storage::fake('public');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Step 1: Ghana Card
    $response = $this->post('/api/v1/vendor-registration/step-1/ghana-card', [
        'ghana_card_front' => UploadedFile::fake()->image('front.jpg'),
        'ghana_card_back' => UploadedFile::fake()->image('back.jpg'),
    ]);

    $response->assertOk();

    $application = VendorApplication::where('user_id', $user->id)->first();
    $this->assertEquals(1, $application->completed_step);

    // Step 2: Business status
    $response = $this->post('/api/v1/vendor-registration/step-2/business-registration', [
        'has_business_certificate' => false,
    ]);

    $response->assertOk();

    // Continue through all steps...
    // Step 3B, Step 4, Payment, Submit

    $application->fresh();
    $this->assertEquals('under_review', $application->status);
}
```

---

The vendor onboarding system ensures quality control while making the registration process smooth and transparent for prospective vendors.
