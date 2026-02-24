# Order API Implementation Guide for Flutter

## Overview

The Surprise Moi order system provides a RESTful API for creating, managing, and tracking orders. Orders are automatically assigned unique identifiers following the format: **VND-WAPZ-[HASH]-[DATE]-[SEQUENCE]**

This guide covers all aspects of order implementation required for the Flutter mobile app.

---

## Order Number Format

Every order is assigned a unique number in the format:

```
VND-WAPZ-FIZU-260204-01
 ↓   ↓    ↓   ↓      ↓
 │   │    │   │      └─ Sequence number (daily, starts at 01)
 │   │    │   └──────── Vendor onboarding date (YYMMDD)
 │   │    └─────────── Unique 4-character vendor hash
 │   └──────────────── Platform source indicator (fixed: WAPZ)
 └─────────────────── Vendor prefix (fixed: VND)
```

### Example Breakdown

- **VND**: Always "VND" (Vendor)
- **WAPZ**: Always "WAPZ" (Platform identifier)
- **FIZU**: Unique vendor hash (generated on vendor onboarding)
- **260204**: Date when vendor was onboarded (Feb 4, 2026)
- **01**: First order from this vendor on this date

---

## API Endpoints

### 1. Get All Orders

Retrieve all orders for the authenticated user.

**Endpoint:**

```
GET /api/v1/orders
```

**Query Parameters:**

```
per_page   (optional): Number of orders per page (default: 15)
status     (optional): Filter by status (pending, confirmed, fulfilled, delivered, cancelled)
search     (optional): Search by order_number or tracking_number
```

**Headers:**

```
Authorization: Bearer {token}
Accept: application/json
```

**Response (200 OK):**

```json
{
  "data": [
    {
      "id": 11,
      "order_number": "VND-WAPZ-FIZU-260204-01",
      "subtotal": 240.00,
      "discount_amount": 0.00,
      "delivery_fee": 0.00,
      "total": 240.00,
      "currency": "USD",
      "status": "pending",
      "payment_status": "unpaid",
      "can_be_paid": true,
      "tracking_number": null,
      "special_instructions": null,
      "occasion": "birthday",
      "scheduled_datetime": null,
      "confirmed_at": null,
      "fulfilled_at": null,
      "delivered_at": null,
      "cancelled_at": null,
      "items": [...],
      "delivery_address": {...},
      "vendor": {
        "id": 21,
        "name": "Premium Gifts Ghana"
      },
      "created_at": "2026-02-09T10:13:42+00:00",
      "updated_at": "2026-02-09T10:13:42+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15,
    ...
  }
}
```

**Flutter Implementation:**

```dart
Future<List<Order>> fetchOrders({int page = 1, int perPage = 15}) async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/v1/orders?page=$page&per_page=$perPage'),
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return (json['data'] as List)
        .map((order) => Order.fromJson(order))
        .toList();
  }
  throw Exception('Failed to load orders');
}
```

---

### 2. Create Order

Create a new order with products/services from a single vendor.

**Endpoint:**

```
POST /api/v1/orders
```

**Request Body:**

```json
{
    "items": [
        {
            "orderable_type": "product",
            "orderable_id": 12,
            "quantity": 2,
            "variant_id": null
        },
        {
            "orderable_type": "service",
            "orderable_id": 5,
            "quantity": 1
        }
    ],
    "delivery_address_id": 7,
    "coupon_code": "SAVE10",
    "special_instructions": "Please wrap as a gift",
    "occasion": "birthday",
    "scheduled_datetime": "2026-02-14T14:00:00Z"
}
```

**Field Validation Rules:**

- `items` (required): At least 1 item
- `items[].orderable_type` (required): Either "product" or "service"
- `items[].orderable_id` (required): Valid product or service ID
- `items[].quantity` (required): Integer ≥ 1
- `items[].variant_id` (optional): Valid product variant ID
- `delivery_address_id` (required): Must belong to authenticated user
- `coupon_code` (optional): Must exist and be valid
- `special_instructions` (optional): Max 500 characters
- `occasion` (optional): One of: birthday, anniversary, random_surprise, graduation, wedding, engagement, baby_shower, valentines_day, mothers_day, fathers_day, christmas, new_year, get_well_soon, congratulations, apology, thank_you, other
- `scheduled_datetime` (optional): Must be in the future

**Response (201 Created):**

```json
{
  "message": "Order created successfully.",
  "order": {
    "id": 11,
    "order_number": "VND-WAPZ-FIZU-260204-01",
    "subtotal": 240.00,
    "discount_amount": 0.00,
    "delivery_fee": 0.00,
    "total": 240.00,
    "currency": "USD",
    "status": "pending",
    "payment_status": "unpaid",
    "can_be_paid": true,
    ...
  }
}
```

**Error Response (422 Unprocessable Entity):**

```json
{
    "message": "Failed to create order: Product is not available.",
    "errors": {
        "items": ["Item not found"]
    }
}
```

**Flutter Implementation:**

```dart
class OrderItem {
  final String orderableType; // "product" or "service"
  final int orderableId;
  final int quantity;
  final int? variantId;

  OrderItem({
    required this.orderableType,
    required this.orderableId,
    required this.quantity,
    this.variantId,
  });

  Map<String, dynamic> toJson() => {
    'orderable_type': orderableType,
    'orderable_id': orderableId,
    'quantity': quantity,
    if (variantId != null) 'variant_id': variantId,
  };
}

Future<Order> createOrder({
  required List<OrderItem> items,
  required int deliveryAddressId,
  String? couponCode,
  String? specialInstructions,
  String? occasion,
  DateTime? scheduledDateTime,
}) async {
  final requestBody = {
    'items': items.map((item) => item.toJson()).toList(),
    'delivery_address_id': deliveryAddressId,
    if (couponCode != null) 'coupon_code': couponCode,
    if (specialInstructions != null) 'special_instructions': specialInstructions,
    if (occasion != null) 'occasion': occasion,
    if (scheduledDateTime != null) 'scheduled_datetime': scheduledDateTime.toIso8601String(),
  };

  final response = await http.post(
    Uri.parse('$baseUrl/api/v1/orders'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: jsonEncode(requestBody),
  );

  if (response.statusCode == 201) {
    final json = jsonDecode(response.body);
    return Order.fromJson(json['order']);
  } else if (response.statusCode == 422) {
    final errorJson = jsonDecode(response.body);
    throw OrderValidationException(errorJson['message']);
  }
  throw Exception('Failed to create order: ${response.statusCode}');
}
```

---

### 3. Get Order Details

Retrieve a specific order by ID.

**Endpoint:**

```
GET /api/v1/orders/{order_id}
```

**Response (200 OK):**

```json
{
    "order": {
        "id": 11,
        "order_number": "VND-WAPZ-FIZU-260204-01",
        "subtotal": 240.0,
        "discount_amount": 0.0,
        "delivery_fee": 0.0,
        "total": 240.0,
        "currency": "USD",
        "status": "pending",
        "payment_status": "unpaid",
        "can_be_paid": true,
        "tracking_number": null,
        "special_instructions": "Please wrap as a gift",
        "occasion": "birthday",
        "scheduled_datetime": null,
        "confirmed_at": null,
        "fulfilled_at": null,
        "delivered_at": null,
        "cancelled_at": null,
        "items": [
            {
                "id": 1,
                "product_id": 12,
                "product_name": "Premium Gift Box - Deluxe",
                "quantity": 2,
                "unit_price": 120.0,
                "subtotal": 240.0
            }
        ],
        "delivery_address": {
            "id": 7,
            "address_line": "123 Main Street, Apartment 5",
            "city": "Accra",
            "state": "Accra",
            "postal_code": "00233",
            "country": "Ghana"
        },
        "vendor": {
            "id": 21,
            "name": "Premium Gifts Ghana"
        },
        "created_at": "2026-02-09T10:13:42+00:00",
        "updated_at": "2026-02-09T10:13:42+00:00"
    }
}
```

**Flutter Implementation:**

```dart
Future<Order> getOrderDetails(int orderId) async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/v1/orders/$orderId'),
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return Order.fromJson(json['order']);
  } else if (response.statusCode == 403) {
    throw UnauthorizedException('You cannot view this order');
  }
  throw Exception('Failed to load order details');
}
```

---

### 4. Update Order Status

Update the status of an order (vendor-only operation).

**Endpoint:**

```
POST /api/v1/orders/{order_id}/status
```

**Request Body:**

```json
{
    "status": "confirmed"
}
```

**Valid Status Values:**

- `confirmed`: Vendor confirms the order
- `fulfilled`: Vendor marks as ready for delivery
- `delivered`: Delivery completed
- `cancelled`: Cancel the order (requires cancellation_reason)

**Response (200 OK):**

```json
{
  "message": "Order status updated.",
  "order": {...}
}
```

**Flutter Implementation:**

```dart
Future<Order> updateOrderStatus(int orderId, String status) async {
  final response = await http.post(
    Uri.parse('$baseUrl/api/v1/orders/$orderId/status'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: jsonEncode({'status': status}),
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return Order.fromJson(json['order']);
  }
  throw Exception('Failed to update order status');
}
```

---

### 5. Cancel Order

Cancel an existing order.

**Endpoint:**

```
POST /api/v1/orders/{order_id}/cancel
```

**Request Body:**

```json
{
    "cancellation_reason": "Found better deal elsewhere"
}
```

**Response (200 OK):**

```json
{
  "message": "Order cancelled successfully.",
  "order": {...}
}
```

**Flutter Implementation:**

```dart
Future<Order> cancelOrder(int orderId, String reason) async {
  final response = await http.post(
    Uri.parse('$baseUrl/api/v1/orders/$orderId/cancel'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: jsonEncode({'cancellation_reason': reason}),
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return Order.fromJson(json['order']);
  }
  throw Exception('Failed to cancel order');
}
```

---

### 6. Track Order

Get real-time tracking information for an order.

**Endpoint:**

```
GET /api/v1/orders/{order_id}/track
```

**Response (200 OK):**

```json
{
    "data": {
        "order_id": 11,
        "order_number": "VND-WAPZ-FIZU-260204-01",
        "status": "in_transit",
        "last_update": "2026-02-09T14:30:00+00:00",
        "estimated_delivery": "2026-02-11T18:00:00+00:00",
        "current_location": "Distribution Center, Accra",
        "tracking_events": [
            {
                "status": "delivered",
                "timestamp": "2026-02-09T10:13:42+00:00",
                "location": "Vendor Location",
                "description": "Order picked up"
            },
            {
                "status": "in_transit",
                "timestamp": "2026-02-09T14:30:00+00:00",
                "location": "Distribution Center, Accra",
                "description": "Package in transit"
            }
        ]
    }
}
```

**Flutter Implementation:**

```dart
Future<OrderTracking> trackOrder(int orderId) async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/v1/orders/$orderId/track'),
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return OrderTracking.fromJson(json['data']);
  }
  throw Exception('Failed to track order');
}
```

---

### 7. Get Order Statistics

Get order statistics for analytics and dashboards.

**Endpoint:**

```
GET /api/v1/orders/statistics
```

**Query Parameters:**

```
period (optional): "day", "week", "month", "year" (default: "month")
```

**Response (200 OK):**

```json
{
    "data": {
        "total_orders": 50,
        "total_revenue": 12000.0,
        "average_order_value": 240.0,
        "pending": 5,
        "confirmed": 10,
        "fulfilled": 20,
        "delivered": 14,
        "cancelled": 1,
        "top_occasions": [
            { "occasion": "birthday", "count": 15 },
            { "occasion": "anniversary", "count": 10 }
        ]
    }
}
```

---

## Data Models

### Order Model

```dart
class Order {
  final int id;
  final String orderNumber;
  final double subtotal;
  final double discountAmount;
  final double deliveryFee;
  final double total;
  final String currency;
  final String status; // pending, confirmed, fulfilled, delivered, cancelled
  final String paymentStatus; // unpaid, pending, paid, failed, refunded
  final bool canBePaid;
  final String? trackingNumber;
  final String? specialInstructions;
  final String? occasion;
  final DateTime? scheduledDateTime;
  final DateTime? confirmedAt;
  final DateTime? fulfilledAt;
  final DateTime? deliveredAt;
  final DateTime? cancelledAt;
  final List<OrderItem> items;
  final Address? deliveryAddress;
  final Vendor vendor;
  final DateTime createdAt;
  final DateTime updatedAt;

  // Add order status helpers
  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isFulfilled => status == 'fulfilled';
  bool get isDelivered => status == 'delivered';
  bool get isCancelled => status == 'cancelled';

  bool get isUnpaid => paymentStatus == 'unpaid';
  bool get isPaid => paymentStatus == 'paid';

  // User-friendly status display
  String get statusDisplay {
    switch (status) {
      case 'pending': return 'Pending';
      case 'confirmed': return 'Confirmed';
      case 'fulfilled': return 'Ready for Delivery';
      case 'delivered': return 'Delivered';
      case 'cancelled': return 'Cancelled';
      default: return 'Unknown';
    }
  }
}
```

### OrderItem Model

```dart
class OrderItem {
  final int id;
  final int productId;
  final String productName;
  final int quantity;
  final double unitPrice;
  final double subtotal;

  double get total => subtotal;
}
```

### Vendor Model

```dart
class Vendor {
  final int id;
  final String name;
  final String? avatar;
  final String? rating;
  final int? reviewCount;
}
```

---

## Best Practices for Flutter Implementation

### 1. State Management

Use Provider or Riverpod for managing order state:

```dart
final ordersProvider = FutureProvider.autoDispose<List<Order>>((ref) async {
  return await orderService.fetchOrders();
});

final selectedOrderProvider = StateProvider<Order?>((ref) => null);
```

### 2. UI Considerations

**Order Number Display:**

- Copy to clipboard button for order number
- Use monospace font for order numbers
- Display in order list and order details

**Status Indicator:**

```dart
Color getStatusColor(String status) {
  switch (status) {
    case 'pending': return Colors.orange;
    case 'confirmed': return Colors.blue;
    case 'fulfilled': return Colors.purple;
    case 'delivered': return Colors.green;
    case 'cancelled': return Colors.red;
    default: return Colors.grey;
  }
}
```

**Progress Timeline:**
Show order progression: Pending → Confirmed → Fulfilled → Delivered

### 3. Error Handling

```dart
try {
  final order = await createOrder(...);
  // Handle success
} on OrderValidationException catch (e) {
  // Show validation errors to user
  _showErrorDialog(e.message);
} catch (e) {
  // Handle network errors
  _showErrorDialog('Network error. Please try again.');
}
```

### 4. Real-time Updates

Consider WebSocket integration for live order status updates:

```dart
// Subscribe to order updates
orderService.subscribeToOrder(orderId).listen((order) {
  // Update UI with new order status
});
```

### 5. Offline Support

Cache order data locally:

```dart
class OrderService {
  late final Box<Order> _orderBox;

  Future<List<Order>> fetchOrders() async {
    try {
      final orders = await _apiService.getOrders();
      await _orderBox.putAll({for (var o in orders) o.id: o});
      return orders;
    } catch (e) {
      return _orderBox.values.toList(); // Return cached data
    }
  }
}
```

---

## Testing Checklist

- [ ] Order creation with single product
- [ ] Order creation with multiple items
- [ ] Order with coupon code
- [ ] Order with scheduled delivery
- [ ] Order with special instructions
- [ ] Fetching order list with pagination
- [ ] Fetching single order details
- [ ] Cancelling order
- [ ] Order status validation
- [ ] Error handling (invalid address, out of stock)
- [ ] Order number formatting validation
- [ ] Tracking order updates

---

## Common Issues & Solutions

**Issue:** "Cannot order from multiple vendors"

- **Solution:** Validate all items belong to the same vendor before submission

**Issue:** "Insufficient stock"

- **Solution:** Check product quantity before adding to cart

**Issue:** "Invalid delivery address"

- **Solution:** Use address selection from user profile

**Issue:** "Coupon expired or invalid"

- **Solution:** Validate coupon before order creation or show error message

---

## Support

For API questions or implementation issues, contact the backend team or refer to the complete API documentation at `/docs/api-reference.md`.
