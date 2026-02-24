# API Reference

Quick reference guide for the Surprise Moi REST API.

## Base URL

```
Production: https://api.surprisemoi.com
Development: http://localhost:8000
```

## API Version

Current version: **v1**

All endpoints are prefixed with `/api/v1/`

## Authentication

### Sanctum Token Authentication

Most endpoints require Bearer token authentication.

**Headers**:

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Get Token** (Login):

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response**:

```json
{
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
        /* user object */
    }
}
```

### Guest Cart Access

For cart operations without authentication, use:

**Headers**:

```
X-Cart-Token: {uuid-token}
```

## Common Response Formats

### Success Response

```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        /* response data */
    }
}
```

### Paginated Response

```json
{
    "data": [
        /* items */
    ],
    "links": {
        "first": "http://api.example.com/items?page=1",
        "last": "http://api.example.com/items?page=10",
        "prev": null,
        "next": "http://api.example.com/items?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

### Error Response

```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

## HTTP Status Codes

- `200 OK` - Success
- `201 Created` - Resource created
- `204 No Content` - Success with no response body
- `400 Bad Request` - Invalid request
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Not authorized for this action
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

## Rate Limiting

Default limits:

- Authentication endpoints: 5 requests per minute
- Payment initiation: 5 requests per minute
- General API: 60 requests per minute

**Rate Limit Headers**:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
Retry-After: 30
```

## Endpoint Categories

### Authentication

- `POST /auth/register` - Register new user
- `POST /auth/login` - Login
- `POST /auth/logout` - Logout
- `POST /auth/verify-phone` - Verify phone with OTP
- `POST /auth/resend-otp` - Resend OTP
- `POST /auth/forgot-password` - Request password reset
- `POST /auth/reset-password` - Reset password
- `GET /auth/user` - Get authenticated user

### Profile

- `GET /profile` - View profile
- `PUT /profile` - Update profile
- `POST /profile/avatar` - Upload avatar
- `DELETE /profile/avatar` - Remove avatar
- `PUT /profile/password` - Change password

### Addresses

- `GET /addresses` - List user addresses
- `POST /addresses` - Create address
- `GET /addresses/{id}` - View address
- `PUT /addresses/{id}` - Update address
- `DELETE /addresses/{id}` - Delete address
- `POST /addresses/{id}/set-default` - Set default address

### Products

- `GET /products` - List products (public)
- `GET /products/{id}` - View product (public)
- `POST /products` - Create product (vendor)
- `PUT /products/{id}` - Update product (vendor)
- `DELETE /products/{id}` - Delete product (vendor)

### Services

- `GET /services` - List services (public)
- `GET /services/{id}` - View service (public)
- `POST /services` - Create service (vendor)
- `PUT /services/{id}` - Update service (vendor)
- `DELETE /services/{id}` - Delete service (vendor)

### Shops

- `GET /shops` - List shops (public)
- `GET /shops/{id}` - View shop (public)
- `GET /shops/{id}/products` - Shop products
- `GET /shops/{id}/services` - Shop services
- `GET /my-shops` - Vendor's shops (auth)
- `POST /shops` - Create shop (vendor)
- `PUT /shops/{id}` - Update shop (vendor)
- `DELETE /shops/{id}` - Delete shop (vendor)

### Cart

- `GET /cart` - View cart
- `POST /cart/items` - Add to cart
- `PATCH /cart/items/{id}` - Update cart item
- `DELETE /cart/items/{id}` - Remove from cart
- `POST /cart/clear` - Clear cart
- `POST /cart/merge` - Merge guest cart with user cart (auth)

### Orders

- `GET /orders` - List orders (auth)
- `POST /orders` - Create order (auth)
- `GET /orders/{id}` - View order (auth)
- `POST /orders/{id}/status` - Update status (vendor)
- `POST /orders/{id}/cancel` - Cancel order (auth)
- `GET /orders/{id}/track` - Track order (auth)
- `GET /orders/statistics` - Order statistics (auth)

### Payments

- `GET /payments` - Payment history (auth)
- `GET /payments/{id}` - View payment (auth)
- `POST /payments/initiate` - Start payment (auth)
- `POST /payments/verify` - Verify payment (auth)
- `POST /payments/webhook` - Paystack webhook (no auth)
- `GET /payments/callback` - Payment callback (no auth)

### Reviews

- `GET /reviews` - User's reviews (auth)
- `POST /reviews` - Create review (auth)
- `GET /reviews/{id}` - View review
- `PUT /reviews/{id}` - Update review (auth)
- `DELETE /reviews/{id}` - Delete review (auth)
- `GET /products/{id}/reviews` - Product reviews (public)
- `GET /services/{id}/reviews` - Service reviews (public)

### Chat

- `GET /chat/conversations` - List conversations (auth)
- `POST /chat/conversations` - Start conversation (auth)
- `GET /chat/conversations/{id}` - View conversation (auth)
- `GET /chat/conversations/{id}/messages` - Get messages (auth)
- `POST /chat/conversations/{id}/messages` - Send message (auth)
- `POST /chat/conversations/{id}/read` - Mark as read (auth)
- `POST /chat/conversations/{id}/typing` - Typing indicator (auth)
- `GET /chat/unread-count` - Unread count (auth)

### Vendor Registration

- `GET /vendor-registration/status` - Application status (auth)
- `POST /vendor-registration/step-1/ghana-card` - Upload Ghana Card (auth)
- `POST /vendor-registration/step-2/business-registration` - Business status (auth)
- `POST /vendor-registration/step-3/registered-documents` - Upload docs (auth)
- `POST /vendor-registration/step-3/unregistered-documents` - Upload docs (auth)
- `POST /vendor-registration/step-4/bespoke-services` - Select services (auth)
- `POST /vendor-registration/payment/initiate` - Pay onboarding fee (auth)
- `POST /vendor-registration/payment/verify` - Verify payment (auth)
- `POST /vendor-registration/submit` - Submit application (auth)

### Vendor Analytics

- `GET /vendor/analytics` - Overview (vendor)
- `GET /vendor/analytics/overview` - Detailed overview (vendor)
- `GET /vendor/analytics/revenue-by-category` - Revenue breakdown (vendor)
- `GET /vendor/analytics/top-products` - Top products (vendor)
- `GET /vendor/analytics/trends` - Sales trends (vendor)

### Vendor Balance

- `GET /vendor/balance` - View balance (vendor)
- `GET /vendor/transactions` - Transaction history (vendor)

### Referral Codes (Influencer)

- `GET /referral-codes` - List codes (influencer)
- `POST /referral-codes` - Create code (influencer)
- `GET /referral-codes/{id}` - View code (influencer)
- `PUT /referral-codes/{id}` - Update code (influencer)
- `DELETE /referral-codes/{id}` - Delete code (influencer)

### Influencer Dashboard

- `GET /influencer/dashboard` - Dashboard overview (influencer)
- `GET /influencer/referrals` - Referral list (influencer)
- `GET /influencer/earnings` - Earnings history (influencer)

### Field Agent

- `GET /field-agent/dashboard` - Dashboard (field_agent)
- `GET /field-agent/targets` - Assigned targets (field_agent)
- `GET /field-agent/earnings` - Earnings (field_agent)

### Marketer

- `GET /marketer/dashboard` - Dashboard (marketer)
- `GET /marketer/targets` - Targets (marketer)
- `GET /marketer/quarterly-earnings` - Quarterly earnings (marketer)

### Payout Requests

- `GET /payout-requests` - List requests (auth)
- `POST /payout-requests` - Create request (auth)
- `GET /payout-requests/{id}` - View request (auth)
- `POST /payout-requests/{id}/cancel` - Cancel request (auth)

### Admin (Admin Only)

- `PUT /admin/vendors/{id}` - Update vendor
- Resource endpoints for:
    - `/admin/categories`
    - `/admin/interests`
    - `/admin/personality-traits`
    - `/admin/targets`
- `GET /admin/payout-requests` - List all payouts
- `POST /admin/payout-requests/{id}/approve` - Approve payout
- `POST /admin/payout-requests/{id}/reject` - Reject payout

### Public Helpers

- `GET /filters` - All filter options
- `GET /filters/categories` - Product categories
- `GET /locations/autocomplete` - Google Maps autocomplete
- `GET /locations/geocode` - Geocode address
- `GET /profile-options/interests` - Available interests
- `GET /profile-options/personality-traits` - Available traits

## Common Query Parameters

### Pagination

```
?page=1
?per_page=15
```

### Filtering

```
?status=active
?category_id=5
?vendor_id=10
```

### Sorting

```
?sort_by=created_at
?sort_direction=desc
```

### Search

```
?search=gift
```

### Date Range

```
?start_date=2026-01-01
?end_date=2026-01-31
```

## Examples

### Create Order

```http
POST /api/v1/orders
Authorization: Bearer {token}
Content-Type: application/json

{
    "items": [
        {
            "orderable_type": "product",
            "orderable_id": 5,
            "variant_id": 12,
            "quantity": 2
        }
    ],
    "delivery_address_id": 3,
    "coupon_code": "SAVE10",
    "special_instructions": "Please gift wrap",
    "occasion": "birthday"
}
```

### Send Chat Message

```http
POST /api/v1/chat/conversations/5/messages
Authorization: Bearer {token}
Content-Type: application/json

{
    "body": "Is this item available?",
    "type": "text"
}
```

### Initiate Payment

```http
POST /api/v1/payments/initiate
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 123,
    "callback_url": "https://app.example.com/payment-success"
}
```

## WebSocket (Laravel Reverb)

### Connection

```javascript
import Echo from 'laravel-echo';

const echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.REVERB_APP_KEY,
    wsHost: process.env.REVERB_HOST,
    wsPort: process.env.REVERB_PORT,
    forceTLS: true,
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    },
});
```

### Subscribe to Channels

```javascript
// Private user channel
echo.private(`App.Models.User.${userId}`).listen('.notification', (event) => {
    console.log('Notification:', event);
});

// Conversation channel
echo.private(`conversation.${conversationId}`)
    .listen('.message.sent', (event) => {
        console.log('New message:', event);
    })
    .listen('.user.typing', (event) => {
        console.log('User typing:', event.is_typing);
    });
```

## Postman Collection

A complete Postman collection is available:
`postman-collections/Surprise_Moi_Complete_API_Collection.json`

Import this into Postman for easy API testing.

## Support

For API issues:

- Check error messages and status codes
- Review relevant documentation section
- Ensure authentication headers are correct
- Verify payload structure matches examples

---

This API follows RESTful principles and uses standard HTTP methods and status codes for intuitive interaction.
