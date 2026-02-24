# Delivery Confirmation & Vendor Payout System

## Overview

This document describes the complete delivery confirmation and vendor payout system implementation for the Surprise Moi platform. The system handles order delivery confirmation using a 4-digit PIN, vendor balance management, payout requests, and admin payout approval workflow.

## Table of Contents

1. [System Flow](#system-flow)
2. [Delivery Confirmation](#delivery-confirmation)
3. [Vendor Balance Management](#vendor-balance-management)
4. [Vendor Payout Requests](#vendor-payout-requests)
5. [Admin Payout Approval](#admin-payout-approval)
6. [API Endpoints](#api-endpoints)
7. [Flutter Implementation Guide](#flutter-implementation-guide)
8. [Data Models](#data-models)

---

## System Flow

### Complete Transaction Lifecycle

```
1. ORDER PLACED
   ↓
2. PAYMENT PROCESSED
   ↓
3. PLATFORM COMMISSION DEDUCTED (12% or 8%)
   ↓
4. VENDOR AMOUNT MOVED TO **PENDING BALANCE**
   ↓
5. ORDER DELIVERED → 4-DIGIT PIN CONFIRMED
   ↓
6. VENDOR AMOUNT MOVED TO **AVAILABLE BALANCE**
   ↓
7. VENDOR REQUESTS PAYOUT
   ↓
8. ADMIN APPROVES PAYOUT
   ↓
9. ADMIN SENDS MOBILE MONEY & MARKS AS PAID
   ↓
10. VENDOR RECEIVES MONEY
```

---

## Delivery Confirmation

### Overview

When an order is delivered, the delivery personnel uses a simple web page to confirm delivery using a 4-digit PIN that was auto-generated when the order was created.

### Order Model Updates

**New Fields Added to Orders Table:**

```php
delivery_pin VARCHAR(4) - Auto-generated unique 4-digit PIN
delivery_confirmed_at TIMESTAMP - When delivery was confirmed
delivery_confirmed_by VARCHAR - Name of delivery person (optional)
```

### Delivery PIN Generation

- **Automatic**: PIN is auto-generated when order is created
- **Format**: Exactly 4 numerical digits (e.g., `4352`, `7891`)
- **Uniqueness**: Guaranteed unique across all active orders

### Delivery Confirmation Process

1. **Customer receives order** → Gives 4-digit PIN to delivery person
2. **Delivery person opens web page** → `/delivery-confirm` (public URL)
3. **Enters**:
    - 4-digit PIN (required)
    - Order Number (required) - Format: `VND-WAPZ-XXXX-XXXXXX-XX`
    - Delivery Person Name (optional)
4. **System verifies** PIN + Order Number combination
5. **Upon successful confirmation**:
    - Order status changes to `delivered`
    - Vendor's **pending balance** → **available balance**
    - Vendor transaction logged
    - Delivery confirmation timestamp recorded

### Public Delivery Confirmation Page

**URL**: `https://yourapp.com/delivery-confirm`

**Features**:

- Beautiful purple gradient UI matching Surprise Moi branding
- Mobile-responsive design
- Real-time validation (PIN must be exactly 4 digits)
- Success animation with checkmark
- Auto-resets form after 3 seconds on success

---

## Vendor Balance Management

### VendorBalance Model

Each vendor has a `vendor_balances` record with the following fields:

```php
vendor_id           INTEGER    - FK to vendor user
pending_balance     DECIMAL    - Money held until delivery confirmed
available_balance   DECIMAL    - Money available for payout
total_earned        DECIMAL    - Lifetime earnings
total_withdrawn     DECIMAL    - Lifetime payouts received
currency            VARCHAR    - Default: GHS
```

### Balance States

| State         | Description                          | Can Request Payout? |
| ------------- | ------------------------------------ | ------------------- |
| **Pending**   | Order paid but not yet delivered     | ❌ No               |
| **Available** | Delivery confirmed, ready for payout | ✅ Yes              |

### Balance Flow Example

```
Order Total: 100.00 GHS
Commission (12%): -12.00 GHS
Vendor Amount: 88.00 GHS

BEFORE DELIVERY:
  pending_balance: +88.00 GHS
  available_balance: 0.00 GHS

AFTER DELIVERY CONFIRMED:
  pending_balance: 0.00 GHS
  available_balance: +88.00 GHS

AFTER PAYOUT REQUESTED (50.00 GHS):
  pending_balance: 0.00 GHS
  available_balance: 38.00 GHS (88 - 50)

AFTER PAYOUT PAID OUT:
  total_withdrawn: +50.00 GHS
```

---

## Vendor Payout Requests

### Creating a Payout Request

**Prerequisites**:

- Vendor must have `available_balance > 0`
- No pending payout requests
- Valid mobile money details

**Process**:

1. Vendor views available balance in app
2. Clicks "Request Payout"
3. Enters:
    - Payout amount (cannot exceed available_balance)
    - Mobile Money Number (e.g., `0244123456`)
    - Mobile Money Provider (MTN, Vodafone, AirtelTigo)
4. System creates `PayoutRequest` with status `pending`
5. Amount is deducted from `available_balance` (reserved)

### PayoutRequest Status Flow

```
PENDING → APPROVED → PAID
   ↓
REJECTED (money returned to available_balance)
```

### Payout Request Fields

```php
id                      INTEGER
user_id                 INTEGER      - Vendor user ID
request_number          VARCHAR      - Auto-generated (e.g., PYT-PMH1YXXCS5)
user_role               VARCHAR      - Always 'vendor' for vendors
amount                  DECIMAL      - Payout amount
currency                VARCHAR      - GHS
payout_method           VARCHAR      - 'mobile_money'
mobile_money_number     VARCHAR      - e.g., 0244123456
mobile_money_provider   VARCHAR      - MTN, Vodafone, AirtelTigo
status                  VARCHAR      - pending/approved/paid/rejected
processed_by            INTEGER      - Admin user ID who processed
processed_at            TIMESTAMP
paid_at                 TIMESTAMP
payment_reference       VARCHAR      - Mobile money transaction ref
rejection_reason        VARCHAR      - If rejected
notes                   TEXT         - Admin notes
```

---

## Admin Payout Approval

### Admin Dashboard - Payout Statistics

Admins can see real-time statistics:

```json
{
    "total_pending": 5,
    "total_approved": 12,
    "total_paid": 108,
    "total_rejected": 2,
    "pending_amount": "1,250.00"
}
```

### Approval Workflow

#### 1. View Pending Payouts

Admin sees list of all pending payout requests with:

- Request number
- Vendor name
- Amount
- Mobile money details
- Request date

#### 2. Approve Payout

**Action**: Admin reviews and approves request

**Result**:

- Status changes: `pending` → `approved`
- `processed_by` set to admin user ID
- `processed_at` timestamp recorded
- Admin notes can be added

#### 3. Send Mobile Money

**External Action**: Admin manually sends money via mobile money provider (MTN, Vodafone, AirtelTigo) to vendor's registered number

#### 4. Mark as Paid

**Action**: Admin confirms money was sent successfully

**Required Fields**:

- Payment reference (mobile money transaction ID)
- Optional admin notes

**Result**:

- Status changes: `approved` → `paid`
- `paid_at` timestamp recorded
- `payment_reference` saved
- Vendor's `total_withdrawn` increased
- Transaction logged

### Rejection Flow

If admin rejects a payout request:

1. Admin provides `rejection_reason` (required)
2. Status changes to `rejected`
3. Amount returned to vendor's `available_balance`
4. Refund transaction logged
5. Vendor notified (implement push notification)

---

## API Endpoints

### Public Endpoints (No Authentication)

#### Confirm Delivery

```http
POST /api/v1/delivery/confirm
Content-Type: application/json

{
  "delivery_pin": "4352",
  "order_number": "VND-WAPZ-SCXH-260204-01",
  "delivery_person_name": "Kwame Mensah" // optional
}
```

**Success Response (200)**:

```json
{
    "success": true,
    "message": "Delivery confirmed successfully!",
    "order": {
        "order_number": "VND-WAPZ-SCXH-260204-01",
        "status": "delivered",
        "delivery_confirmed_at": "2026-02-09 12:01:34",
        "delivery_confirmed_by": "Kwame Mensah",
        "vendor_payout_amount": "88.00"
    }
}
```

**Error Response (422)**:

```json
{
    "success": false,
    "message": "Invalid PIN or order number"
}
```

#### Verify Delivery PIN (Pre-validation)

```http
POST /api/v1/delivery/verify
Content-Type: application/json

{
  "delivery_pin": "4352",
  "order_number": "VND-WAPZ-SCXH-260204-01"
}
```

**Success Response (200)**:

```json
{
    "success": true,
    "message": "Valid PIN and order number",
    "order": {
        "order_number": "VND-WAPZ-SCXH-260204-01",
        "status": "pending",
        "vendor_payout_amount": "88.00"
    }
}
```

---

### Vendor Endpoints (Requires Authentication)

**Authentication**: `Authorization: Bearer {vendor_token}`

**Role Required**: `vendor`

#### Get Vendor Balance

```http
GET /api/v1/vendor/balance
```

**Response (200)**:

```json
{
    "success": true,
    "balance": {
        "available_balance": "100.00",
        "pending_balance": "50.00",
        "total_earned": "1,500.00",
        "total_withdrawn": "800.00",
        "currency": "GHS"
    }
}
```

#### Request Payout

```http
POST /api/v1/vendor/payouts/request
Content-Type: application/json

{
  "amount": 50.00,
  "mobile_money_number": "0244123456",
  "mobile_money_provider": "MTN"  // MTN, Vodafone, AirtelTigo
}
```

**Success Response (201)**:

```json
{
    "success": true,
    "message": "Payout request created successfully",
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "amount": "50.00",
        "currency": "GHS",
        "status": "pending",
        "payout_method": "mobile_money",
        "mobile_money_number": "0244123456",
        "mobile_money_provider": "MTN",
        "created_at": "2026-02-09 12:04:40"
    },
    "updated_balance": {
        "available_balance": "50.00",
        "pending_balance": "50.00"
    }
}
```

**Error Responses**:

```json
// Insufficient balance
{
    "success": false,
    "message": "Insufficient available balance",
    "errors": {
        "amount": ["Insufficient balance. Available: 30.00 GHS"]
    }
}
```

```json
// Pending request exists
{
    "success": false,
    "message": "You already have a pending payout request"
}
```

#### Get Payout History

```http
GET /api/v1/vendor/payouts?status=all&page=1
```

**Query Parameters**:

- `status`: `all`, `pending`, `approved`, `paid`, `rejected` (default: `all`)
- `page`: Page number (default: 1)

**Response (200)**:

```json
{
    "success": true,
    "payouts": {
        "current_page": 1,
        "data": [
            {
                "id": 3,
                "request_number": "PYT-PMH1YXXCS5",
                "amount": "50.00",
                "status": "paid",
                "payout_method": "mobile_money",
                "mobile_money_number": "0244123456",
                "mobile_money_provider": "MTN",
                "created_at": "2026-02-09 12:04:40",
                "processed_at": "2026-02-09 12:05:47",
                "paid_at": "2026-02-09 12:08:39",
                "payment_reference": "MOMO-REF-2026020912-MTN-50GHS"
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

#### Get Single Payout Details

```http
GET /api/v1/vendor/payouts/{id}
```

**Response (200)**:

```json
{
    "success": true,
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "amount": "50.00",
        "currency": "GHS",
        "status": "paid",
        "payout_method": "mobile_money",
        "mobile_money_number": "0244123456",
        "mobile_money_provider": "MTN",
        "created_at": "2026-02-09 12:04:40",
        "processed_at": "2026-02-09 12:05:47",
        "paid_at": "2026-02-09 12:08:39",
        "payment_reference": "MOMO-REF-2026020912-MTN-50GHS",
        "notes": "Approved for vendor Mateo Mraz"
    }
}
```

---

### Admin Endpoints (Requires Authentication)

**Authentication**: `Authorization: Bearer {admin_token}`

**Role Required**: `admin` or `super_admin`

#### List All Payout Requests

```http
GET /api/v1/admin/payouts?status=pending&search=PYT-&page=1
```

**Query Parameters**:

- `status`: `pending`, `approved`, `paid`, `rejected` (optional, returns all if omitted)
- `search`: Search by request number, vendor name, or email
- `page`: Page number (default: 1)

**Response (200)**:

```json
{
    "success": true,
    "payouts": {
        "current_page": 1,
        "data": [
            {
                "id": 3,
                "request_number": "PYT-PMH1YXXCS5",
                "user_id": 36,
                "user_name": "Mateo Mraz",
                "user_email": "awisoky@example.com",
                "user_phone": "0244123456",
                "user_role": "vendor",
                "amount": "50.00",
                "currency": "GHS",
                "status": "pending",
                "payout_method": "mobile_money",
                "mobile_money_number": "0244123456",
                "mobile_money_provider": "MTN",
                "created_at": "2026-02-09 12:04:40",
                "processed_by_name": null,
                "processed_at": null
            }
        ],
        "total": 1,
        "per_page": 15
    },
    "statistics": {
        "total_pending": 1,
        "total_approved": 0,
        "total_paid": 0,
        "total_rejected": 0,
        "pending_amount": "50.00"
    }
}
```

#### View Payout Details (Admin)

```http
GET /api/v1/admin/payouts/{id}
```

**Response (200)**:

```json
{
    "success": true,
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "user_id": 36,
        "user": {
            "id": 36,
            "name": "Mateo Mraz",
            "email": "awisoky@example.com",
            "phone": "0244123456",
            "role": "vendor"
        },
        "amount": "50.00",
        "status": "pending",
        "created_at": "2026-02-09 12:04:40"
    },
    "vendor_balance": {
        "available_balance": "50.00",
        "pending_balance": "-12.00",
        "total_earned": "88.00"
    }
}
```

#### Approve Payout

```http
POST /api/v1/admin/payouts/{id}/approve
Content-Type: application/json

{
  "admin_notes": "Approved for vendor Mateo Mraz"  // optional
}
```

**Success Response (200)**:

```json
{
    "success": true,
    "message": "Payout request approved successfully.",
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "status": "approved",
        "processed_by": 1,
        "processed_at": "2026-02-09 12:05:47",
        "notes": "Approved for vendor Mateo Mraz"
    }
}
```

**Error Response (400)**:

```json
{
    "success": false,
    "message": "Only pending payout requests can be approved."
}
```

#### Reject Payout

```http
POST /api/v1/admin/payouts/{id}/reject
Content-Type: application/json

{
  "rejection_reason": "Invalid mobile money number provided"  // required
}
```

**Success Response (200)**:

```json
{
    "success": true,
    "message": "Payout request rejected successfully.",
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "status": "rejected",
        "rejection_reason": "Invalid mobile money number provided",
        "processed_by": 1,
        "processed_at": "2026-02-09 12:06:15"
    }
}
```

#### Mark Payout as Paid

```http
POST /api/v1/admin/payouts/{id}/mark-paid
Content-Type: application/json

{
  "payment_reference": "MOMO-REF-2026020912-MTN-50GHS",  // required
  "admin_notes": "Mobile money transfer successful to 0244123456 via MTN"  // optional
}
```

**Success Response (200)**:

```json
{
    "success": true,
    "message": "Payout marked as paid successfully.",
    "payout": {
        "id": 3,
        "request_number": "PYT-PMH1YXXCS5",
        "status": "paid",
        "payment_reference": "MOMO-REF-2026020912-MTN-50GHS",
        "paid_at": "2026-02-09 12:08:39",
        "notes": "Mobile money transfer successful to 0244123456 via MTN"
    }
}
```

**Error Response (400)**:

```json
{
    "success": false,
    "message": "Only approved payout requests can be marked as paid."
}
```

---

## Flutter Implementation Guide

### 1. Update Order Model

```dart
class Order {
  final int id;
  final String orderNumber;
  final String status;
  final double totalAmount;
  final double commissionAmount;
  final double vendorPayoutAmount;

  // NEW FIELDS
  final String? deliveryPin;              // 4-digit PIN
  final DateTime? deliveryConfirmedAt;    // Delivery timestamp
  final String? deliveryConfirmedBy;      // Delivery person name

  // ... other fields

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'],
      orderNumber: json['order_number'],
      status: json['status'],
      totalAmount: double.parse(json['total_amount'] ?? '0'),
      commissionAmount: double.parse(json['commission_amount'] ?? '0'),
      vendorPayoutAmount: double.parse(json['vendor_payout_amount'] ?? '0'),
      deliveryPin: json['delivery_pin'],
      deliveryConfirmedAt: json['delivery_confirmed_at'] != null
          ? DateTime.parse(json['delivery_confirmed_at'])
          : null,
      deliveryConfirmedBy: json['delivery_confirmed_by'],
      // ... other fields
    );
  }
}
```

### 2. Create VendorBalance Model

```dart
class VendorBalance {
  final double availableBalance;
  final double pendingBalance;
  final double totalEarned;
  final double totalWithdrawn;
  final String currency;

  VendorBalance({
    required this.availableBalance,
    required this.pendingBalance,
    required this.totalEarned,
    required this.totalWithdrawn,
    required this.currency,
  });

  factory VendorBalance.fromJson(Map<String, dynamic> json) {
    return VendorBalance(
      availableBalance: double.parse(json['available_balance'] ?? '0'),
      pendingBalance: double.parse(json['pending_balance'] ?? '0'),
      totalEarned: double.parse(json['total_earned'] ?? '0'),
      totalWithdrawn: double.parse(json['total_withdrawn'] ?? '0'),
      currency: json['currency'] ?? 'GHS',
    );
  }

  double get totalBalance => availableBalance + pendingBalance;

  bool get canRequestPayout => availableBalance > 0;
}
```

### 3. Create PayoutRequest Model

```dart
enum PayoutStatus {
  pending,
  approved,
  paid,
  rejected,
  cancelled;

  static PayoutStatus fromString(String status) {
    return PayoutStatus.values.firstWhere(
      (e) => e.name == status.toLowerCase(),
      orElse: () => PayoutStatus.pending,
    );
  }
}

enum MobileMoneyProvider {
  mtn('MTN'),
  vodafone('Vodafone'),
  airtelTigo('AirtelTigo');

  final String displayName;
  const MobileMoneyProvider(this.displayName);
}

class PayoutRequest {
  final int id;
  final String requestNumber;
  final double amount;
  final String currency;
  final PayoutStatus status;
  final String payoutMethod;
  final String mobileMoneyNumber;
  final String mobileMoneyProvider;
  final DateTime createdAt;
  final DateTime? processedAt;
  final DateTime? paidAt;
  final String? paymentReference;
  final String? rejectionReason;
  final String? notes;

  PayoutRequest({
    required this.id,
    required this.requestNumber,
    required this.amount,
    required this.currency,
    required this.status,
    required this.payoutMethod,
    required this.mobileMoneyNumber,
    required this.mobileMoneyProvider,
    required this.createdAt,
    this.processedAt,
    this.paidAt,
    this.paymentReference,
    this.rejectionReason,
    this.notes,
  });

  factory PayoutRequest.fromJson(Map<String, dynamic> json) {
    return PayoutRequest(
      id: json['id'],
      requestNumber: json['request_number'],
      amount: double.parse(json['amount']),
      currency: json['currency'],
      status: PayoutStatus.fromString(json['status']),
      payoutMethod: json['payout_method'],
      mobileMoneyNumber: json['mobile_money_number'],
      mobileMoneyProvider: json['mobile_money_provider'],
      createdAt: DateTime.parse(json['created_at']),
      processedAt: json['processed_at'] != null
          ? DateTime.parse(json['processed_at'])
          : null,
      paidAt: json['paid_at'] != null
          ? DateTime.parse(json['paid_at'])
          : null,
      paymentReference: json['payment_reference'],
      rejectionReason: json['rejection_reason'],
      notes: json['notes'],
    );
  }

  bool get isPending => status == PayoutStatus.pending;
  bool get isApproved => status == PayoutStatus.approved;
  bool get isPaid => status == PayoutStatus.paid;
  bool get isRejected => status == PayoutStatus.rejected;

  String get statusText {
    switch (status) {
      case PayoutStatus.pending:
        return 'Pending Review';
      case PayoutStatus.approved:
        return 'Approved - Payment in Progress';
      case PayoutStatus.paid:
        return 'Paid';
      case PayoutStatus.rejected:
        return 'Rejected';
      case PayoutStatus.cancelled:
        return 'Cancelled';
    }
  }

  Color get statusColor {
    switch (status) {
      case PayoutStatus.pending:
        return Colors.orange;
      case PayoutStatus.approved:
        return Colors.blue;
      case PayoutStatus.paid:
        return Colors.green;
      case PayoutStatus.rejected:
        return Colors.red;
      case PayoutStatus.cancelled:
        return Colors.grey;
    }
  }
}
```

### 4. Vendor Balance Service

```dart
class VendorBalanceService {
  final ApiService _api;

  VendorBalanceService(this._api);

  Future<VendorBalance> getBalance() async {
    final response = await _api.get('/api/v1/vendor/balance');

    if (response['success'] == true) {
      return VendorBalance.fromJson(response['balance']);
    }

    throw Exception(response['message'] ?? 'Failed to fetch balance');
  }

  Future<PayoutRequest> requestPayout({
    required double amount,
    required String mobileMoneyNumber,
    required MobileMoneyProvider provider,
  }) async {
    final response = await _api.post(
      '/api/v1/vendor/payouts/request',
      data: {
        'amount': amount,
        'mobile_money_number': mobileMoneyNumber,
        'mobile_money_provider': provider.displayName,
      },
    );

    if (response['success'] == true) {
      return PayoutRequest.fromJson(response['payout']);
    }

    throw Exception(response['message'] ?? 'Failed to request payout');
  }

  Future<List<PayoutRequest>> getPayoutHistory({
    String status = 'all',
    int page = 1,
  }) async {
    final response = await _api.get(
      '/api/v1/vendor/payouts',
      queryParameters: {'status': status, 'page': page},
    );

    if (response['success'] == true) {
      final List payouts = response['payouts']['data'];
      return payouts.map((json) => PayoutRequest.fromJson(json)).toList();
    }

    throw Exception('Failed to fetch payout history');
  }
}
```

### 5. Vendor Balance Screen (Example)

```dart
class VendorBalanceScreen extends StatefulWidget {
  @override
  _VendorBalanceScreenState createState() => _VendorBalanceScreenState();
}

class _VendorBalanceScreenState extends State<VendorBalanceScreen> {
  late VendorBalanceService _balanceService;
  VendorBalance? _balance;
  List<PayoutRequest> _payoutHistory = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _balanceService = VendorBalanceService(context.read<ApiService>());
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);

    try {
      final balance = await _balanceService.getBalance();
      final history = await _balanceService.getPayoutHistory();

      setState(() {
        _balance = balance;
        _payoutHistory = history;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  void _requestPayout() {
    if (_balance == null || !_balance!.canRequestPayout) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('No available balance to withdraw')),
      );
      return;
    }

    // Show payout request dialog
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) => PayoutRequestSheet(
        maxAmount: _balance!.availableBalance,
        onRequestCreated: () {
          Navigator.pop(context);
          _loadData(); // Reload data
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: Text('My Balance')),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text('My Balance'),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh),
            onPressed: _loadData,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Balance Card
              Card(
                elevation: 4,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  padding: EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Text(
                        'Available Balance',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 16,
                        ),
                      ),
                      SizedBox(height: 8),
                      Text(
                        '${_balance!.currency} ${_balance!.availableBalance.toStringAsFixed(2)}',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 36,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      SizedBox(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _buildBalanceInfo(
                            'Pending',
                            '${_balance!.pendingBalance.toStringAsFixed(2)}',
                          ),
                          _buildBalanceInfo(
                            'Total Earned',
                            '${_balance!.totalEarned.toStringAsFixed(2)}',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              SizedBox(height: 16),

              // Request Payout Button
              ElevatedButton.icon(
                onPressed: _balance!.canRequestPayout ? _requestPayout : null,
                icon: Icon(Icons.payment),
                label: Text('Request Payout'),
                style: ElevatedButton.styleFrom(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),

              SizedBox(height: 24),

              // Payout History
              Text(
                'Payout History',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              SizedBox(height: 8),

              if (_payoutHistory.isEmpty)
                Center(
                  child: Padding(
                    padding: EdgeInsets.all(32),
                    child: Text(
                      'No payout requests yet',
                      style: TextStyle(color: Colors.grey),
                    ),
                  ),
                )
              else
                ..._payoutHistory.map((payout) => _buildPayoutCard(payout)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBalanceInfo(String label, String amount) {
    return Column(
      children: [
        Text(
          label,
          style: TextStyle(color: Colors.white70, fontSize: 12),
        ),
        SizedBox(height: 4),
        Text(
          amount,
          style: TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  Widget _buildPayoutCard(PayoutRequest payout) {
    return Card(
      margin: EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: payout.statusColor.withOpacity(0.2),
          child: Icon(
            payout.isPaid ? Icons.check_circle : Icons.access_time,
            color: payout.statusColor,
          ),
        ),
        title: Text(
          '${payout.currency} ${payout.amount.toStringAsFixed(2)}',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(payout.requestNumber),
            SizedBox(height: 4),
            Text(
              payout.statusText,
              style: TextStyle(color: payout.statusColor),
            ),
            if (payout.isPaid && payout.paymentReference != null)
              Text(
                'Ref: ${payout.paymentReference}',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
          ],
        ),
        trailing: Text(
          _formatDate(payout.createdAt),
         style: TextStyle(fontSize: 12, color: Colors.grey),
        ),
        onTap: () => _showPayoutDetails(payout),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }

  void _showPayoutDetails(PayoutRequest payout) {
    // Show full payout details dialog
  }
}
```

### 6. Payout Request Sheet (Example)

```dart
class PayoutRequestSheet extends StatefulWidget {
  final double maxAmount;
  final VoidCallback onRequestCreated;

  PayoutRequestSheet({
    required this.maxAmount,
    required this.onRequestCreated,
  });

  @override
  _PayoutRequestSheetState createState() => _PayoutRequestSheetState();
}

class _PayoutRequestSheetState extends State<PayoutRequestSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _phoneController = TextEditingController();
  MobileMoneyProvider _provider = MobileMoneyProvider.mtn;
  bool _loading = false;

  @override
  void dispose() {
    _amountController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _submitRequest() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _loading = true);

    try {
      final service = VendorBalanceService(context.read<ApiService>());
      await service.requestPayout(
        amount: double.parse(_amountController.text),
        mobileMoneyNumber: _phoneController.text,
        provider: _provider,
      );

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Payout request submitted successfully!')),
      );

      widget.onRequestCreated();
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        padding: EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Request Payout',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              SizedBox(height: 8),
              Text(
                'Available: GHS ${widget.maxAmount.toStringAsFixed(2)}',
                style: TextStyle(color: Colors.grey),
              ),
              SizedBox(height: 24),

              // Amount Field
              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'Amount (GHS)',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.money),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter amount';
                  }
                  final amount = double.tryParse(value);
                  if (amount == null || amount <= 0) {
                    return 'Please enter valid amount';
                  }
                  if (amount > widget.maxAmount) {
                    return 'Amount exceeds available balance';
                  }
                  return null;
                },
              ),

              SizedBox(height: 16),

              // Mobile Money Provider
              DropdownButtonFormField<MobileMoneyProvider>(
                value: _provider,
                decoration: InputDecoration(
                  labelText: 'Mobile Money Provider',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.phone_android),
                ),
                items: MobileMoneyProvider.values.map((provider) {
                  return DropdownMenuItem(
                    value: provider,
                    child: Text(provider.displayName),
                  );
                }).toList(),
                onChanged: (value) {
                  if (value != null) {
                    setState(() => _provider = value);
                  }
                },
              ),

              SizedBox(height: 16),

              // Mobile Money Number
              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: 'Mobile Money Number',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.phone),
                  hintText: 'e.g., 0244123456',
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter mobile money number';
                  }
                  if (!RegExp(r'^0[0-9]{9}$').hasMatch(value)) {
                    return 'Please enter valid 10-digit number';
                  }
                  return null;
                },
              ),

              SizedBox(height: 24),

              // Submit Button
              ElevatedButton(
                onPressed: _loading ? null : _submitRequest,
                child: _loading
                    ? SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text('Submit Request'),
                style: ElevatedButton.styleFrom(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

### 7. Admin Payout Management Screen

```dart
// For Admin Dashboard - List all pending payouts
class AdminPayoutListScreen extends StatefulWidget {
  @override
  _AdminPayoutListScreenState createState() => _AdminPayoutListScreenState();
}

class _AdminPayoutListScreenState extends State<AdminPayoutListScreen> {
  final _api = ApiService();
  List<PayoutRequest> _payouts = [];
  Map<String, dynamic>? _statistics;
  String _filterStatus = 'pending';
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadPayouts();
  }

  Future<void> _loadPayouts() async {
    setState(() => _loading = true);

    try {
      final response = await _api.get(
        '/api/v1/admin/payouts',
        queryParameters: {'status': _filterStatus},
      );

      if (response['success'] == true) {
        final List payoutsData = response['payouts']['data'];
        setState(() {
          _payouts = payoutsData.map((json) => PayoutRequest.fromJson(json)).toList();
          _statistics = response['statistics'];
          _loading = false;
        });
      }
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  Future<void> _approvePayout(PayoutRequest payout) async {
    try {
      final response = await _api.post('/api/v1/admin/payouts/${payout.id}/approve');

      if (response['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Payout approved successfully')),
        );
        _loadPayouts();
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  Future<void> _markAsPaid(PayoutRequest payout, String paymentRef) async {
    try {
      final response = await _api.post(
        '/api/v1/admin/payouts/${payout.id}/mark-paid',
        data: {'payment_reference': paymentRef},
      );

      if (response['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Payout marked as paid')),
        );
        _loadPayouts();
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Payout Requests'),
      ),
      body: Column(
        children: [
          // Statistics
          if (_statistics != null)
            Container(
              padding: EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _buildStat('Pending', _statistics!['total_pending'].toString()),
                  _buildStat('Approved', _statistics!['total_approved'].toString()),
                  _buildStat('Paid', _statistics!['total_paid'].toString()),
                ],
              ),
            ),

          // Filter Tabs
          TabBar(
            tabs: [
              Tab(text: 'Pending'),
              Tab(text: 'Approved'),
              Tab(text: 'Paid'),
            ],
            onTap: (index) {
              setState(() {
                _filterStatus = ['pending', 'approved', 'paid'][index];
              });
              _loadPayouts();
            },
          ),

          // Payout List
          Expanded(
            child: _loading
                ? Center(child: CircularProgressIndicator())
                : ListView.builder(
                    itemCount: _payouts.length,
                    itemBuilder: (context, index) {
                      final payout = _payouts[index];
                      return _buildPayoutCard(payout);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildStat(String label, String value) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
        Text(label, style: TextStyle(color: Colors.grey)),
      ],
    );
  }

  Widget _buildPayoutCard(PayoutRequest payout) {
    // Build payout card with approve/reject/mark-paid buttons
    return Card(
      margin: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: ExpansionTile(
        title: Text('${payout.currency} ${payout.amount.toStringAsFixed(2)}'),
        subtitle: Text('${payout.requestNumber} - Vendor Name Here'),
        children: [
          Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Mobile Money: ${payout.mobileMoneyNumber}'),
                Text('Provider: ${payout.mobileMoneyProvider}'),
                SizedBox(height: 16),
                if (payout.isPending)
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _approvePayout(payout),
                          child: Text('Approve'),
                        ),
                      ),
                      SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {/* Reject */},
                          child: Text('Reject'),
                        ),
                      ),
                    ],
                  ),
                if (payout.isApproved)
                  ElevatedButton(
                    onPressed: () {
                      // Show dialog to enter payment reference
                      _showMarkAsPaidDialog(payout);
                    },
                    child: Text('Mark as Paid'),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showMarkAsPaidDialog(PayoutRequest payout) {
    final controller = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Mark as Paid'),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(
            labelText: 'Payment Reference',
            hintText: 'MOMO-REF-XXXXXXXXXX',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              if (controller.text.isNotEmpty) {
                Navigator.pop(context);
                _markAsPaid(payout, controller.text);
              }
            },
            child: Text('Confirm'),
          ),
        ],
      ),
    );
  }
}
```

---

## Data Models

### Database Tables

#### orders (Updated)

```sql
delivery_pin             VARCHAR(4)   - Auto-generated unique 4-digit PIN
delivery_confirmed_at    TIMESTAMP    - When delivery was confirmed
delivery_confirmed_by    VARCHAR      - Name of delivery person
```

#### vendor_balances

```sql
id                  SERIAL PRIMARY KEY
vendor_id           INTEGER REFERENCES users(id)
pending_balance     DECIMAL(10,2) DEFAULT 0  - Money held until delivery
available_balance   DECIMAL(10,2) DEFAULT 0  - Money available for payout
total_earned        DECIMAL(10,2) DEFAULT 0  - Lifetime earnings
total_withdrawn     DECIMAL(10,2) DEFAULT 0  - Lifetime payouts
currency            VARCHAR(3) DEFAULT 'GHS'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### payout_requests

```sql
id                      SERIAL PRIMARY KEY
user_id                 INTEGER REFERENCES users(id)
request_number          VARCHAR UNIQUE  - Auto-generated (PYT-XXXXXXXXXX)
user_role               VARCHAR  - 'vendor', 'influencer', 'field_agent', 'marketer'
amount                  DECIMAL(10,2)
currency                VARCHAR(3) DEFAULT 'GHS'
payout_method           VARCHAR  - 'mobile_money'
mobile_money_number     VARCHAR
mobile_money_provider   VARCHAR  - MTN, Vodafone, AirtelTigo
status                  VARCHAR  - pending/approved/paid/rejected/cancelled
rejection_reason        TEXT
processed_by            INTEGER REFERENCES users(id)
processed_at            TIMESTAMP
paid_at                 TIMESTAMP
payment_reference       VARCHAR  - Mobile money transaction reference
notes                   TEXT
created_at              TIMESTAMP
updated_at              TIMESTAMP
deleted_at              TIMESTAMP (soft delete)
```

#### vendor_transactions

```sql
id                  SERIAL PRIMARY KEY
vendor_id           INTEGER REFERENCES users(id)
order_id            INTEGER REFERENCES orders(id) NULLABLE
transaction_number  VARCHAR UNIQUE  - Auto-generated (VTX-XXXXXXXXXX)
type                VARCHAR  - credit_sale/release_funds/payout/refund/adjustment
amount              DECIMAL(10,2)  - Positive for credits, negative for debits
currency            VARCHAR(3) DEFAULT 'GHS'
status              VARCHAR  - pending/completed/failed/cancelled
description         TEXT
metadata            JSONB
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

## Important Notes

### 1. Security Considerations

- **Delivery PIN**: Should only be shown to customer and delivery person
- **Payout Requests**: Vendor cannot have multiple pending requests
- **Admin Actions**: All admin actions are logged with `processed_by` field
- **Balance Validation**: System prevents payout requests exceeding available balance

### 2. Business Logic

- **Commission Deduction**: Happens immediately on order payment
- **Pending to Available**: Triggered ONLY on delivery confirmation
- **Payout Reserve**: When payout requested, amount is deducted from available balance immediately (not returned unless rejected)
- **Total Withdrawn**: Only incremented when payout is marked as PAID

### 3. Testing Checklist

- [ ] Order creation generates unique 4-digit PIN
- [ ] Delivery confirmation moves balance from pending to available
- [ ] Vendor can request payout with valid mobile money details
- [ ] Admin can view all pending payouts with statistics
- [ ] Admin can approve payout (status changes to approved)
- [ ] Admin can reject payout (money returned to vendor)
- [ ] Admin can mark as paid with payment reference
- [ ] Payout history shows correct status for vendors
- [ ] Balance calculations are accurate throughout lifecycle

### 4. Mobile Money Integration (Future Enhancement)

Currently, admins manually send mobile money and enter payment reference. Future enhancement could integrate with:

- **MTN Mobile Money API**
- **Vodafone Cash API**
- **AirtelTigo Money API**

This would allow automatic disbursements and real-time status updates.

---

## Support & Questions

For questions or issues with this implementation, please contact the backend team or refer to the main API documentation at `/docs/README.md`.

---

**Last Updated**: February 9, 2026  
**Version**: 1.0  
**Author**: Backend Development Team
