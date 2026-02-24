# Payment & Financial Systems

This document covers payment processing, vendor financial management, and payout operations.

## Overview

The platform uses **Paystack** as the payment gateway, supporting multiple payment methods:

- Card payments (Visa, Mastercard, Verve)
- Bank transfers
- Mobile money

Financial tracking includes:

- Order payments from customers
- Vendor balance management
- Commission calculations
- Payout requests and processing

## Payment Model

**Location**: `app/Models/Payment.php`

### Attributes

```php
[
    'user_id',                 // Customer who made payment
    'order_id',                // Associated order
    'reference',               // Internal reference (PAY-XXXXXXXXXXXXXXXX)
    'paystack_reference',      // Paystack's transaction reference
    'authorization_url',       // Redirect URL for payment
    'access_code',             // Paystack access code
    'amount',                  // Amount in currency units (decimal)
    'amount_in_kobo',          // Amount in smallest unit (integer)
    'currency',                // GHS, USD, etc.
    'channel',                 // 'card', 'bank', 'mobile_money'
    'payment_method_type',     // Card type or bank name
    'status',                  // Payment status
    'card_last4',              // Last 4 digits of card
    'card_type',               // visa, mastercard, etc.
    'card_exp_month',
    'card_exp_year',
    'card_bank',               // Issuing bank
    'mobile_money_number',     // For mobile money payments
    'mobile_money_provider',   // MTN, Vodafone, AirtelTigo
    'metadata',                // Additional data (JSON)
    'log',                     // Transaction log (JSON)
    'gateway_response',        // Paystack response message
    'ip_address',              // Customer's IP
    'failure_reason',          // Reason if failed
    'paid_at',                 // Successful payment timestamp
    'verified_at',             // Verification timestamp
]
```

### Payment Statuses

```php
const STATUS_PENDING = 'pending';       // Initiated, awaiting payment
const STATUS_PROCESSING = 'processing'; // Being processed
const STATUS_SUCCESS = 'success';       // Successfully paid
const STATUS_FAILED = 'failed';         // Payment failed
const STATUS_ABANDONED = 'abandoned';   // User abandoned payment
const STATUS_REVERSED = 'reversed';     // Payment reversed/refunded
const STATUS_CANCELLED = 'cancelled';   // Cancelled by user/admin
```

### Helper Methods

```php
// Check if payment is successful
$payment->isSuccessful(): bool

// Mark payment as successful
$payment->markAsSuccessful(array $additionalData = [])

// Mark payment as failed
$payment->markAsFailed(string $reason, array $additionalData = [])

// Generate unique reference
Payment::generateReference(): string  // Returns "PAY-XXXXXXXXXXXXXXXX"
```

### Relationships

```php
$payment->user()   // BelongsTo User (customer)
$payment->order()  // BelongsTo Order
```

## Paystack Integration

**Service**: `app/Services/PaystackService.php`  
**Controller**: `app/Http/Controllers/Api/V1/PaymentController.php`

### Configuration

**File**: `config/services.php`

```php
'paystack' => [
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'currency' => env('PAYSTACK_CURRENCY', 'GHS'),
    'callback_url' => env('PAYSTACK_CALLBACK_URL'),
],
```

### Payment Flow

#### 1. Initiate Payment

**Endpoint**: `POST /api/v1/payments/initiate`

**Request**:

```json
{
    "order_id": 123,
    "callback_url": "https://app.example.com/payment/callback"
}
```

**Process**:

```php
// In PaystackService
public function initializeTransaction(Order $order, User $user, ?string $callbackUrl): array
{
    // Generate unique reference
    $reference = Payment::generateReference();

    // Convert to kobo/pesewas (smallest unit)
    $amountInKobo = (int) round($order->total * 100);

    // Build metadata for Paystack
    $metadata = [
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'user_id' => $user->id,
        'custom_fields' => [
            ['display_name' => 'Order Number', 'value' => $order->order_number],
            ['display_name' => 'Customer Name', 'value' => $user->name],
        ],
    ];

    // API call to Paystack
    $response = Http::withToken($this->secretKey)
        ->post("{$this->baseUrl}/transaction/initialize", [
            'email' => $user->email,
            'amount' => $amountInKobo,
            'currency' => $this->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl ?? config('services.paystack.callback_url'),
            'metadata' => $metadata,
            'channels' => ['card', 'bank', 'mobile_money'],
        ]);

    if ($response->successful()) {
        $data = $response->json('data');

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'reference' => $reference,
            'authorization_url' => $data['authorization_url'],
            'access_code' => $data['access_code'],
            'amount' => $order->total,
            'amount_in_kobo' => $amountInKobo,
            'currency' => $this->currency,
            'status' => Payment::STATUS_PENDING,
            'ip_address' => request()->ip(),
            'metadata' => $metadata,
        ]);

        // Update order
        $order->update(['payment_status' => 'pending']);

        return ['success' => true, 'data' => $data, 'payment' => $payment];
    }

    return ['success' => false, 'message' => 'Payment initialization failed'];
}
```

**Response**:

```json
{
    "success": true,
    "data": {
        "authorization_url": "https://checkout.paystack.com/abc123",
        "access_code": "abc123xyz",
        "reference": "PAY-ABCDEF1234567890"
    },
    "payment": {
        /* Payment object */
    }
}
```

**Frontend Flow**:

1. Redirect user to `authorization_url`
2. User completes payment on Paystack
3. Paystack redirects to `callback_url`
4. Frontend calls verification endpoint

#### 2. Verify Payment

**Endpoint**: `POST /api/v1/payments/verify`

**Request**:

```json
{
    "reference": "PAY-ABCDEF1234567890"
}
```

**Process**:

```php
public function verifyTransaction(string $reference): array
{
    $payment = Payment::where('reference', $reference)->first();

    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found'];
    }

    // If already verified, return early
    if ($payment->isSuccessful()) {
        return ['success' => true, 'payment' => $payment];
    }

    // Verify with Paystack
    $response = Http::withToken($this->secretKey)
        ->get("{$this->baseUrl}/transaction/verify/{$reference}");

    if ($response->successful() && $response->json('status') === true) {
        $data = $response->json('data');
        return $this->processVerificationResponse($payment, $data);
    }

    return ['success' => false, 'message' => 'Verification failed'];
}
```

**Verification Processing**:

```php
protected function processVerificationResponse(Payment $payment, array $data): array
{
    $paystackStatus = $data['status'];  // 'success', 'failed', 'abandoned'
    $channel = $data['channel'];        // 'card', 'bank', 'mobile_money'

    // Extract payment details
    $authorization = $data['authorization'] ?? [];
    $cardDetails = [
        'card_last4' => $authorization['last4'] ?? null,
        'card_type' => $authorization['card_type'] ?? null,
        'card_bank' => $authorization['bank'] ?? null,
        // ... more details
    ];

    if ($paystackStatus === 'success') {
        // Verify amount matches
        $expectedAmount = $payment->amount_in_kobo;
        $receivedAmount = $data['amount'];

        if ($receivedAmount < $expectedAmount) {
            $payment->markAsFailed('Amount mismatch');
            return ['success' => false, 'message' => 'Amount mismatch'];
        }

        // Mark payment as successful
        $payment->markAsSuccessful($cardDetails);

        // Update order
        $payment->order->update(['payment_status' => 'paid']);

        // Confirm order if pending
        if ($payment->order->status === 'pending') {
            $payment->order->markAsConfirmed();
        }

        // Credit vendor's balance
        $this->vendorBalanceService->creditPendingBalance($payment->order);

        // Calculate referral commissions
        $this->referralService->calculateCommission($payment->order);

        return ['success' => true, 'payment' => $payment];
    }

    // Payment failed
    $payment->markAsFailed($data['gateway_response']);
    $payment->order->update(['payment_status' => 'failed']);

    return ['success' => false, 'message' => $data['gateway_response']];
}
```

**Response** (Success):

```json
{
    "success": true,
    "data": {
        "status": "success",
        "reference": "PAY-ABCDEF1234567890",
        "amount": 100.0,
        "channel": "card",
        "paid_at": "2026-02-03T14:30:00Z"
    },
    "payment": {
        /* Full payment object */
    }
}
```

#### 3. Webhook Handler

**Endpoint**: `POST /api/v1/payments/webhook` (No auth required)

Paystack sends real-time events to this endpoint.

**Webhook Signature Verification**:

```php
public function webhook(Request $request): JsonResponse
{
    // Verify webhook signature
    $signature = $request->header('X-Paystack-Signature');
    $payload = $request->getContent();

    $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);

    if (!hash_equals($signature, $computedSignature)) {
        Log::warning('Invalid Paystack webhook signature');
        return response()->json(['message' => 'Invalid signature'], 400);
    }

    // Process event
    $event = $request->input('event');
    $data = $request->input('data');

    $result = $this->paystackService->handleWebhook([
        'event' => $event,
        'data' => $data,
    ]);

    return response()->json($result);
}
```

**Supported Events**:

- `charge.success` - Payment successful
- `charge.failed` - Payment failed
- `transfer.success` - Payout successful
- `transfer.failed` - Payout failed

#### 4. Payment Callback

**Endpoint**: `GET /api/v1/payments/callback`

After completing payment on Paystack, user is redirected here.

```php
public function callback(Request $request): RedirectResponse
{
    $reference = $request->query('reference');

    if (!$reference) {
        return redirect()->to(config('app.frontend_url') . '/payment/failed');
    }

    // Verify the payment
    $result = $this->paystackService->verifyTransaction($reference);

    if ($result['success']) {
        return redirect()->to(
            config('app.frontend_url') . "/payment/success?reference={$reference}"
        );
    }

    return redirect()->to(
        config('app.frontend_url') . "/payment/failed?reference={$reference}"
    );
}
```

### Retry Failed Payment

**Endpoint**: `POST /api/v1/payments/{payment}/retry`

Creates a new payment transaction for a failed payment.

```php
public function retry(Payment $payment): JsonResponse
{
    if ($payment->isSuccessful()) {
        return response()->json(['message' => 'Payment already successful'], 422);
    }

    if ($payment->order->payment_status === 'paid') {
        return response()->json(['message' => 'Order already paid'], 422);
    }

    // Initialize new payment
    $result = $this->paystackService->initializeTransaction(
        $payment->order,
        $payment->user,
        request()->input('callback_url')
    );

    return response()->json($result);
}
```

### Rate Limiting

Payment initiation is rate-limited to prevent abuse:

```php
// Max 5 payment initiations per minute per user
$key = 'payment-initiate:' . $userId;

if (RateLimiter::tooManyAttempts($key, 5)) {
    $seconds = RateLimiter::availableIn($key);
    return response()->json([
        'message' => "Too many attempts. Try again in {$seconds} seconds."
    ], 429);
}

RateLimiter::hit($key, 60);
```

## Vendor Balance System

### VendorBalance Model

**Location**: `app/Models/VendorBalance.php`

Tracks vendor's financial status.

```php
[
    'vendor_id',          // Vendor user ID
    'pending_balance',    // Funds awaiting order delivery (decimal)
    'available_balance',  // Funds ready for withdrawal (decimal)
    'total_earned',       // Lifetime earnings (decimal)
    'total_withdrawn',    // Total payouts received (decimal)
    'currency',
]
```

**Computed Property**:

```php
$vendorBalance->getTotalBalanceAttribute(): float
// Returns pending_balance + available_balance
```

### VendorTransaction Model

**Location**: `app/Models/VendorTransaction.php`

Audit trail of all balance changes.

```php
[
    'vendor_id',
    'order_id',            // Related order (if applicable)
    'type',                // 'credit', 'debit', 'payout', 'refund'
    'amount',              // Transaction amount (decimal)
    'balance_before',      // Balance before transaction
    'balance_after',       // Balance after transaction
    'description',         // Human-readable description
    'metadata',            // Additional data (JSON)
]
```

### VendorBalanceService

**Location**: `app/Services/VendorBalanceService.php`

#### Credit Pending Balance

Called when payment is verified (order paid but not delivered):

```php
public function creditPendingBalance(Order $order): void
{
    $balance = VendorBalance::firstOrCreate(['vendor_id' => $order->vendor_id], [
        'pending_balance' => 0,
        'available_balance' => 0,
        'total_earned' => 0,
        'total_withdrawn' => 0,
        'currency' => $order->currency,
    ]);

    $balanceBefore = $balance->pending_balance;

    // Add to pending balance
    $balance->increment('pending_balance', $order->total);

    // Record transaction
    VendorTransaction::create([
        'vendor_id' => $order->vendor_id,
        'order_id' => $order->id,
        'type' => 'credit',
        'amount' => $order->total,
        'balance_before' => $balanceBefore,
        'balance_after' => $balance->fresh()->pending_balance,
        'description' => "Payment received for order #{$order->order_number}",
    ]);
}
```

#### Move to Available Balance

Called when order is marked as delivered:

```php
public function moveToAvailableBalance(Order $order): void
{
    $balance = VendorBalance::where('vendor_id', $order->vendor_id)->first();

    if (!$balance) {
        Log::error('Vendor balance not found', ['vendor_id' => $order->vendor_id]);
        return;
    }

    // Calculate vendor's share (platform takes commission)
    $platformCommission = $order->total * 0.15;  // 15% platform fee
    $vendorAmount = $order->total - $platformCommission;

    // Move from pending to available
    $balance->decrement('pending_balance', $order->total);
    $balance->increment('available_balance', $vendorAmount);
    $balance->increment('total_earned', $vendorAmount);

    // Record transaction
    VendorTransaction::create([
        'vendor_id' => $order->vendor_id,
        'order_id' => $order->id,
        'type' => 'credit',
        'amount' => $vendorAmount,
        'description' => "Order #{$order->order_number} delivered. Commission: {$platformCommission}",
        'metadata' => [
            'gross_amount' => $order->total,
            'platform_commission' => $platformCommission,
            'net_amount' => $vendorAmount,
        ],
    ]);
}
```

**Trigger Point**:

In `OrderController`:

```php
public function updateStatus(Order $order, UpdateOrderStatusRequest $request): JsonResponse
{
    // ... validation

    if ($request->status === 'delivered' && $order->payment_status === 'paid') {
        // Move pending balance to available
        $this->vendorBalanceService->moveToAvailableBalance($order);
    }

    $order->update(['status' => $request->status]);

    return response()->json(['order' => new OrderResource($order)]);
}
```

### View Balance

**Endpoint**: `GET /api/v1/vendor/balance`

**Response**:

```json
{
    "balance": {
        "pending_balance": 500.0,
        "available_balance": 1200.5,
        "total_balance": 1700.5,
        "total_earned": 5430.0,
        "total_withdrawn": 3729.5,
        "currency": "GHS"
    }
}
```

### View Transactions

**Endpoint**: `GET /api/v1/vendor/transactions`

**Query Parameters**:

- `type` - Filter by transaction type
- `start_date`, `end_date` - Date range
- `per_page` - Pagination

**Response**:

```json
{
    "data": [
        {
            "id": 123,
            "type": "credit",
            "amount": 85.0,
            "description": "Order #ORD-ABC123 delivered...",
            "balance_before": 1115.5,
            "balance_after": 1200.5,
            "created_at": "2026-02-03T10:30:00Z"
        }
    ],
    "meta": {
        /* pagination */
    }
}
```

## Payout System

**Model**: `app/Models/PayoutRequest.php`  
**Controller**: `app/Http/Controllers/Api/V1/PayoutRequestController.php`  
**Service**: `app/Services/PayoutService.php`

### PayoutRequest Model

```php
[
    'user_id',             // Vendor, influencer, field_agent, or marketer
    'amount',              // Requested amount (decimal)
    'currency',
    'status',              // 'pending', 'approved', 'rejected', 'completed'
    'payment_method',      // 'bank_transfer', 'mobile_money'
    'account_details',     // Bank/mobile money details (JSON)
    'admin_notes',         // Admin comments
    'processed_at',
    'processed_by',        // Admin user ID
]
```

### Request Payout

**Endpoint**: `POST /api/v1/payout-requests`

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

- Amount must not exceed available balance
- Minimum payout amount (e.g., $10)
- User must have verified account details

**Process**:

```php
public function store(Request $request): JsonResponse
{
    $user = $request->user();
    $balance = VendorBalance::where('vendor_id', $user->id)->first();

    if (!$balance || $balance->available_balance < $request->amount) {
        return response()->json(['message' => 'Insufficient balance'], 422);
    }

    $payoutRequest = PayoutRequest::create([
        'user_id' => $user->id,
        'amount' => $request->amount,
        'currency' => $balance->currency,
        'status' => 'pending',
        'payment_method' => $request->payment_method,
        'account_details' => $request->account_details,
    ]);

    // Optionally reserve amount
    $balance->decrement('available_balance', $request->amount);

    return response()->json(['payout_request' => $payoutRequest], 201);
}
```

### Admin: Approve Payout

**Endpoint**: `POST /api/v1/admin/payout-requests/{payoutRequest}/approve`

**Request**:

```json
{
    "notes": "Processed via bank transfer"
}
```

**Process**:

```php
public function approve(PayoutRequest $payoutRequest, Request $request): JsonResponse
{
    if ($payoutRequest->status !== 'pending') {
        return response()->json(['message' => 'Already processed'], 422);
    }

    // Initiate Paystack transfer
    $result = $this->payoutService->initiateTransfer($payoutRequest);

    if (!$result['success']) {
        return response()->json(['message' => $result['message']], 422);
    }

    $payoutRequest->update([
        'status' => 'approved',
        'admin_notes' => $request->notes,
        'processed_at' => now(),
        'processed_by' => $request->user()->id,
    ]);

    // Record debit transaction
    VendorTransaction::create([
        'vendor_id' => $payoutRequest->user_id,
        'type' => 'payout',
        'amount' => -$payoutRequest->amount,
        'description' => "Payout request #{$payoutRequest->id} approved",
    ]);

    return response()->json(['payout_request' => $payoutRequest]);
}
```

### Paystack Transfer (Payout)

```php
// In PayoutService
public function initiateTransfer(PayoutRequest $payoutRequest): array
{
    $recipient = $this->createOrGetRecipient($payoutRequest);

    if (!$recipient['success']) {
        return $recipient;
    }

    $response = Http::withToken($this->secretKey)
        ->post("{$this->baseUrl}/transfer", [
            'source' => 'balance',
            'amount' => $payoutRequest->amount * 100,  // Convert to kobo
            'recipient' => $recipient['recipient_code'],
            'reason' => "Payout for request #{$payoutRequest->id}",
        ]);

    if ($response->successful() && $response->json('status') === true) {
        return ['success' => true, 'data' => $response->json('data')];
    }

    return ['success' => false, 'message' => 'Transfer initiation failed'];
}
```

## Testing

### Payment Flow Test

```php
public function test_complete_payment_flow(): void
{
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'total' => 100]);

    Sanctum::actingAs($user);

    // Initiate payment
    $response = $this->postJson('/api/v1/payments/initiate', [
        'order_id' => $order->id,
    ]);

    $response->assertOk();
    $reference = $response->json('data.reference');

    // Simulate successful Paystack verification
    $this->mock(PaystackService::class, function ($mock) use ($reference) {
        $mock->shouldReceive('verifyTransaction')
            ->with($reference)
            ->andReturn(['success' => true, 'payment' => Payment::first()]);
    });

    // Verify payment
    $response = $this->postJson('/api/v1/payments/verify', [
        'reference' => $reference,
    ]);

    $response->assertOk();
    $this->assertEquals('paid', $order->fresh()->payment_status);
}
```

---

This payment system provides secure, auditable financial transactions with proper separation between customer payments, vendor balances, and platform commissions.
