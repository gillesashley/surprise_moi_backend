# Architecture Overview

This document explains the high-level architecture, design patterns, and organizational structure of the Surprise Moi platform.

## Technology Stack

### Backend

- **Laravel 12.47.0** - Modern PHP framework with streamlined structure
- **PHP 8.2.29** - Using constructor property promotion and strict typing
- **PostgreSQL** - Primary database
- **Laravel Sanctum 4** - API token authentication
- **Laravel Fortify 1** - Authentication scaffolding
- **Laravel Reverb 1** - WebSocket server for real-time features

### Frontend

- **React 19** - UI library
- **TypeScript** - Type-safe JavaScript
- **Inertia.js 2** - Server-driven SPA framework
- **Tailwind CSS 4** - Utility-first CSS framework
- **Vite** - Frontend build tool
- **Laravel Wayfinder** - Type-safe route helpers for TypeScript

### Third-Party Services

- **Paystack** - Payment processing
- **Google Maps API** - Location services
- **Kairos Afrika SMS** - SMS/OTP delivery

## Application Architecture

### Laravel 12 Structure

Laravel 12 uses a streamlined file structure:

```
app/
├── Console/Commands/       # Artisan commands (auto-discovered)
├── Events/                 # Broadcastable events
├── Http/
│   ├── Controllers/        # Web and API controllers
│   ├── Middleware/         # Custom middleware
│   ├── Requests/           # Form request validation
│   └── Resources/          # API resource transformers
├── Models/                 # Eloquent models
├── Policies/               # Authorization policies
├── Providers/              # Service providers
└── Services/               # Business logic layer

bootstrap/
├── app.php                 # Application bootstrap (middleware, exceptions)
└── providers.php           # Service provider registration

config/                     # Configuration files
database/
├── factories/              # Model factories for testing
├── migrations/             # Database migrations
└── seeders/                # Database seeders

routes/
├── api.php                 # API routes (prefixed with /api)
├── channels.php            # Broadcast channel authorization
├── console.php             # Console routes
├── settings.php            # Settings routes
└── web.php                 # Web routes (Inertia.js pages)

resources/
├── css/                    # Stylesheets
├── js/                     # React components and TypeScript
└── views/                  # Blade templates (minimal, mostly for email)

tests/
├── Feature/                # Feature tests
└── Unit/                   # Unit tests
```

### Design Patterns

#### 1. **Service Layer Pattern**

Business logic is extracted into dedicated service classes in `app/Services/`:

- `PaystackService` - Payment processing
- `VendorBalanceService` - Vendor financial operations
- `CartService` - Cart management
- `ReferralService` - Referral tracking
- `EarningService` - Earnings calculations
- `GoogleMapsService` - Location operations

**Example**:

```php
// In Controller
public function __construct(protected PaystackService $paystackService) {}

public function initiate(Request $request): JsonResponse
{
    $result = $this->paystackService->initializeTransaction($order, $user);
    // ...
}
```

#### 2. **Repository Pattern (Light)**

Models use Eloquent relationships and scopes rather than full repositories:

```php
// Scopes in models
public function scopeActive($query) {
    return $query->where('is_active', true);
}

// Usage
$shops = Shop::active()->with('products')->get();
```

#### 3. **Resource Pattern**

API responses are transformed using Eloquent API Resources in `app/Http/Resources/`:

```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // ...
        ];
    }
}
```

#### 4. **Form Request Validation**

All validation logic is in dedicated Form Request classes in `app/Http/Requests/`:

```php
class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // ...
        ];
    }
}
```

#### 5. **Event-Driven Architecture**

Real-time features use Laravel Events and Broadcasting:

```php
// Event
class MessageSent implements ShouldBroadcast
{
    public function broadcastOn(): array {
        return [new PrivateChannel('conversation.' . $this->message->conversation_id)];
    }
}

// Triggered in controller
broadcast(new MessageSent($message));
```

## Request Lifecycle

### API Request Flow (REST)

```
HTTP Request
    ↓
API Middleware (auth:sanctum, throttle)
    ↓
Route Match (/api/v1/...)
    ↓
Controller Method
    ↓
Form Request Validation (optional)
    ↓
Service Layer (business logic)
    ↓
Model/Database Operations
    ↓
API Resource Transformation
    ↓
JSON Response
```

### Web Request Flow (Inertia)

```
HTTP Request
    ↓
Web Middleware (auth, dashboard)
    ↓
Route Match (/dashboard/...)
    ↓
Controller Method
    ↓
Inertia::render('Component', $props)
    ↓
React Component Hydration
    ↓
HTML Response
```

## Authentication & Authorization

### API Authentication (Sanctum)

- Token-based authentication for mobile/SPA
- Tokens stored in `personal_access_tokens` table
- Middleware: `auth:sanctum`

```php
// Login returns token
$token = $user->createToken('mobile-app')->plainTextToken;

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Routes here
});
```

### Web Authentication (Fortify)

- Session-based authentication for admin dashboard
- Middleware: `auth`

### Authorization

- Role-based access: `role` field in users table
- Custom middleware: `admin`, `dashboard`
- Policy classes in `app/Policies/`

**Roles**:

- `customer` - Regular users
- `vendor` - Shop owners
- `admin` - Platform administrators
- `super_admin` - Full access
- `influencer` - Referral marketing
- `field_agent` - On-ground marketing
- `marketer` - Regional marketing

## Database Architecture

### Key Design Decisions

#### 1. **Soft Deletes**

Most models use `SoftDeletes` trait to preserve data:

- Products, Services, Orders, Shops

#### 2. **Polymorphic Relationships**

- `Reviews` can belong to Product or Service via `reviewable_type` and `reviewable_id`

#### 3. **Currency in Cents**

Financial amounts stored as integers in cents for precision:

```php
// Cart model
'subtotal_cents' => 'integer',  // Stored as cents
public function getSubtotalAttribute(): float {
    return $this->subtotal_cents / 100;  // Accessor returns decimal
}
```

#### 4. **UUID Tokens**

Guest carts use UUID tokens for session tracking:

```php
static::creating(function ($cart) {
    if (empty($cart->cart_token) && empty($cart->user_id)) {
        $cart->cart_token = Str::uuid();
    }
});
```

#### 5. **Generated Identifiers**

Orders and payments auto-generate unique references:

```php
// Order
'order_number' => 'ORD-' . strtoupper(Str::random(10))

// Payment
'reference' => 'PAY-' . strtoupper(Str::random(16))
```

## Real-Time Architecture

### Laravel Reverb

- WebSocket server built into Laravel
- Handles broadcasting for chat and notifications
- Configured in `config/reverb.php`

### Broadcasting Flow

```
1. User sends chat message
2. Message saved to database
3. Event dispatched: MessageSent
4. Reverb broadcasts to channel: conversation.{id}
5. Frontend listens and displays message instantly
```

### Channels

Defined in `routes/channels.php`:

- `App.Models.User.{id}` - User private channel
- `conversation.{conversationId}` - Chat conversation channel

## API Versioning

### Current Version: v1

All API routes are prefixed with `/api/v1/`:

```php
Route::prefix('v1')->group(function () {
    // All API routes
});
```

### Future Versioning Strategy

When breaking changes are needed:

1. Create new version: `/api/v2/`
2. Maintain v1 for backward compatibility
3. Document migration path
4. Sunset old version with deprecation warnings

## Configuration Management

### Environment Variables

All environment-specific config in `.env`:

```
APP_NAME="Surprise Moi"
DB_CONNECTION=pgsql
PAYSTACK_SECRET_KEY=sk_...
REVERB_APP_ID=...
```

### Config Files

Never use `env()` directly outside `config/` files:

```php
// ❌ Bad
$appName = env('APP_NAME');

// ✅ Good
$appName = config('app.name');
```

## Deployment Architecture

### Production Stack (Typical)

```
Load Balancer
    ↓
Nginx (Web Server)
    ↓
PHP-FPM (Laravel)
    ↓
PostgreSQL Database
    ↓
Redis (Cache/Queue)
    ↓
Supervisor (Queue Workers, Reverb)
```

### Queue System

- Driver: Database or Redis
- Workers managed by Supervisor
- Jobs: Payment processing, email sending, notifications

### File Storage

- Local: `storage/app/public/`
- Production: S3-compatible storage (recommended)
- Symlink: `public/storage` → `storage/app/public`

## Performance Considerations

### Eager Loading

Prevent N+1 queries using `with()`:

```php
$products = Product::with(['category', 'vendor', 'images'])->get();
```

### Caching Strategy

- Config cache: `php artisan config:cache`
- Route cache: `php artisan route:cache`
- View cache: `php artisan view:cache`
- Query results cached using Redis

### Database Indexing

Key indexes on:

- Foreign keys (vendor_id, user_id, etc.)
- Frequently queried fields (status, created_at)
- Unique fields (email, phone, order_number)

## Error Handling

### Exception Handler

Centralized in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    // Custom exception handling
})
```

### API Error Responses

Consistent JSON format:

```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error"]
    }
}
```

### Logging

- Log files: `storage/logs/laravel.log`
- Channels: Stack, daily, slack
- Configured in `config/logging.php`

## Security Measures

### Input Validation

- All inputs validated via Form Requests
- SQL injection prevented by Eloquent ORM
- XSS protection via Blade escaping

### CSRF Protection

- Web routes protected by CSRF middleware
- API routes use token authentication

### Rate Limiting

```php
// Payment initiation limited
RateLimiter::hit('payment-initiate:' . $userId, 60); // 5 per minute
```

### Password Hashing

- Bcrypt hashing (Laravel default)
- Minimum 8 characters enforced

## Code Style & Standards

### PHP Standards

- PSR-12 coding standard
- Laravel Pint for formatting: `vendor/bin/pint --dirty`

### Type Declarations

Always use return types and parameter types:

```php
protected function calculateTotal(Order $order): float
{
    return $order->subtotal + $order->delivery_fee;
}
```

### Documentation

- PHPDoc blocks for complex methods
- Inline comments only for complex logic

---

This architecture is designed for scalability, maintainability, and developer experience. Understanding these patterns will help you navigate and contribute to the codebase effectively.
