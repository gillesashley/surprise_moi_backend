# Authentication & Users

This document covers the authentication system, user management, and role-based access control in Surprise Moi.

## Overview

The platform uses a dual authentication strategy:

- **Laravel Sanctum** for API token authentication (mobile apps, SPAs)
- **Laravel Fortify** for web session authentication (admin dashboard)

## User Model

**Location**: `app/Models/User.php`

### User Roles

The `role` field determines user permissions:

| Role          | Description             | Access                                           |
| ------------- | ----------------------- | ------------------------------------------------ |
| `customer`    | Regular buyers          | Shop, order, chat with vendors                   |
| `vendor`      | Shop owners             | Manage shops, products, services, view analytics |
| `admin`       | Platform administrators | Manage content, approve vendors                  |
| `super_admin` | Full system access      | All admin functions + system settings            |
| `influencer`  | Referral marketers      | Generate referral codes, earn commissions        |
| `field_agent` | On-ground marketers     | Achieve targets, earn commissions                |
| `marketer`    | Regional marketers      | Manage campaigns, track quarterly earnings       |

### User Attributes

```php
protected $fillable = [
    'name',
    'email',
    'phone',
    'password',
    'avatar',
    'provider',        // OAuth provider (e.g., 'google', 'facebook')
    'provider_id',     // OAuth provider user ID
    'role',
    'date_of_birth',
    'gender',
    'bio',
    'favorite_color',
    'favorite_music_genre',
    'is_popular',      // Featured vendor flag
];
```

### Key Relationships

```php
// Products and services (for vendors)
$user->products()          // HasMany Product
$user->services()          // HasMany Service
$user->shops()             // HasMany Shop

// Orders
$user->orders()            // Orders as customer
$user->vendorOrders()      // Orders received as vendor

// Vendor system
$user->vendorApplications()           // All applications
$user->latestVendorApplication()      // Most recent application
$user->hasApprovedVendorApplication() // Check approval status
$user->vendorBalance()                // Financial balance
$user->vendorTransactions()           // Transaction history

// Profile
$user->interests()         // BelongsToMany Interest
$user->personalityTraits() // BelongsToMany PersonalityTrait
$user->addresses()         // HasMany Address
$user->reviews()           // HasMany Review

// Chat
$user->customerConversations() // Conversations as customer
$user->vendorConversations()   // Conversations as vendor
$user->sentMessages()          // All sent messages
```

### Helper Methods

```php
$user->isVendor()          // Returns bool
$user->isCustomer()        // Returns bool
$user->isAdmin()           // Returns bool
$user->isSuperAdmin()      // Returns bool
```

## Registration Flow

**Controller**: `app/Http/Controllers/Api/V1/AuthController.php`  
**Endpoint**: `POST /api/v1/auth/register`

### Process

1. **Validate Input**
    - Request validated via `RegisterRequest`
    - Required: name, email, phone, password
    - Optional: role (defaults to 'customer')

2. **Create User**

    ```php
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'password' => $request->password, // Auto-hashed
        'role' => $request->role,
    ]);
    ```

3. **Send OTP**
    - SMS OTP sent via `KairosAfrikaSmsService`
    - Phone number must be verified before full access

4. **Generate Token**

    ```php
    $token = $user->createToken('mobile-app')->plainTextToken;
    ```

5. **Return Response**
    ```json
    {
        "success": true,
        "message": "Account created successfully...",
        "data": {
            "user": { ... },
            "token": "1|abc123...",
            "token_type": "Bearer",
            "otp_sent": true
        }
    }
    ```

## Phone Verification (OTP)

### Send OTP

**Endpoint**: `POST /api/v1/auth/resend-otp`

Sends a One-Time Password to the user's phone number via SMS.

**Service**: `app/Services/KairosAfrikaSmsService.php`

```php
public function sendOtp(string $phoneNumber): array
{
    // Integrates with Kairos Afrika SMS API
}
```

### Verify OTP

**Endpoint**: `POST /api/v1/auth/verify-phone`

**Request**:

```json
{
    "phone": "+233123456789",
    "code": "123456"
}
```

**Process**:

1. Find user by phone number
2. Check if already verified
3. Validate OTP with Kairos Afrika API
4. Mark phone as verified: `phone_verified_at = now()`

**Response**:

```json
{
    "success": true,
    "message": "Phone number verified successfully",
    "data": {
        "user": {
            "phone_verified_at": "2026-02-03T10:30:00Z"
        }
    }
}
```

## Login Flow

**Endpoint**: `POST /api/v1/auth/login`

### Process

1. **Validate Credentials**

    ```php
    // Check email or phone + password
    $user = User::where('email', $request->email)
                ->orWhere('phone', $request->email)
                ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }
    ```

2. **Check Verification Status**
    - Phone must be verified (`phone_verified_at` not null)
    - If not verified, return error with option to resend OTP

3. **Create Token**

    ```php
    $token = $user->createToken('mobile-app')->plainTextToken;
    ```

4. **Return User + Token**

## Token Management

### Token Creation

```php
// Default token
$token = $user->createToken('mobile-app')->plainTextToken;

// Token with abilities (permissions)
$token = $user->createToken('admin-panel', ['admin:*'])->plainTextToken;
```

### Using Tokens

**Headers**:

```
Authorization: Bearer 1|abc123xyz...
```

**Middleware**:

```php
Route::middleware('auth:sanctum')->group(function () {
    // Protected routes
});
```

### Viewing Tokens

**Endpoint**: `GET /api/v1/auth/tokens`

Returns all active tokens for the authenticated user.

### Revoking Tokens

**Single Token**:

```php
$request->user()->currentAccessToken()->delete();
```

**All Tokens** (logout everywhere):  
**Endpoint**: `POST /api/v1/auth/logout-all`

```php
$request->user()->tokens()->delete();
```

## Password Reset

### Request Reset Link

**Endpoint**: `POST /api/v1/auth/forgot-password`

**Request**:

```json
{
    "email": "user@example.com"
}
```

Sends password reset link via email using Laravel's built-in password reset system.

### Reset Password

**Endpoint**: `POST /api/v1/auth/reset-password`

**Request**:

```json
{
    "token": "reset-token",
    "email": "user@example.com",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

Uses `Password::reset()` to validate token and update password.

## Profile Management

**Controller**: `app/Http/Controllers/Api/V1/ProfileController.php`

### View Profile

**Endpoint**: `GET /api/v1/profile`

Returns authenticated user's profile with relationships:

```php
$user->load([
    'interests',
    'personalityTraits',
    'musicGenres',
    'shops',
]);
```

### Update Profile

**Endpoint**: `PUT /api/v1/profile`

**Updateable Fields**:

- name
- email
- phone
- date_of_birth
- gender
- bio
- favorite_color
- favorite_music_genre
- interests (array of IDs)
- personality_traits (array of IDs)
- music_genres (array of IDs)

**Example**:

```json
{
    "name": "John Doe",
    "bio": "Gift enthusiast",
    "interests": [1, 3, 5],
    "personality_traits": [2, 4]
}
```

### Update Avatar

**Endpoint**: `POST /api/v1/profile/avatar`

**Request**: Multipart form with `avatar` file

**Process**:

1. Validate image (jpeg, jpg, png, gif, max 2MB)
2. Delete old avatar if exists
3. Store new avatar in `public/avatars`
4. Update `avatar` field with file path

**Response**:

```json
{
    "message": "Avatar updated successfully",
    "avatar_url": "/storage/avatars/abc123.jpg"
}
```

### Delete Avatar

**Endpoint**: `DELETE /api/v1/profile/avatar`

Removes avatar file and clears `avatar` field.

### Update Password

**Endpoint**: `PUT /api/v1/profile/password`

**Request**:

```json
{
    "current_password": "oldpass",
    "password": "newpass",
    "password_confirmation": "newpass"
}
```

Validates current password before updating.

## Address Management

**Controller**: `app/Http/Controllers/Api/V1/AddressController.php`  
**Model**: `app/Models/Address.php`

Users can save multiple delivery addresses.

### Address Fields

```php
[
    'user_id',
    'label',              // e.g., "Home", "Office"
    'full_name',
    'phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country',
    'latitude',           // From Google Maps
    'longitude',
    'is_default',
]
```

### Endpoints

- `GET /api/v1/addresses` - List user's addresses
- `POST /api/v1/addresses` - Create new address
- `GET /api/v1/addresses/{address}` - View single address
- `PUT /api/v1/addresses/{address}` - Update address
- `DELETE /api/v1/addresses/{address}` - Delete address
- `POST /api/v1/addresses/{address}/set-default` - Set as default

### Default Address Logic

When setting an address as default:

```php
// Unset all other defaults for this user
Address::where('user_id', $user->id)
       ->where('id', '!=', $address->id)
       ->update(['is_default' => false]);

// Set this one as default
$address->update(['is_default' => true]);
```

## Authorization Middleware

### Custom Middleware

**Location**: `app/Http/Middleware/`

#### AdminMiddleware

Restricts access to admin and super_admin roles:

```php
if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
    abort(403, 'Unauthorized');
}
```

**Usage**:

```php
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin-only routes
});
```

#### DashboardMiddleware

Allows access to users with dashboard privileges:

```php
$allowedRoles = ['admin', 'super_admin', 'influencer', 'field_agent', 'marketer'];

if (!in_array($request->user()->role, $allowedRoles)) {
    abort(403);
}
```

#### RoleMiddleware

Dynamic role checking:

```php
Route::middleware(['auth:sanctum', 'role:influencer'])->group(function () {
    // Influencer-only routes
});
```

## Profile Options (Public Endpoints)

For dropdown menus during registration/profile editing:

### Interests

**Endpoint**: `GET /api/v1/profile-options/interests`

Returns all available interests (e.g., "Sports", "Music", "Technology").

### Personality Traits

**Endpoint**: `GET /api/v1/profile-options/personality-traits`

Returns all personality traits (e.g., "Outgoing", "Creative", "Analytical").

## Email Verification (Legacy)

The platform originally used email verification but now primarily uses phone OTP. Email verification routes still exist for backward compatibility:

- `GET /api/v1/auth/email/verify/{id}/{hash}` - Verify email via signed URL
- `POST /api/v1/auth/resend-verification` - Resend verification email

## Security Best Practices

### Rate Limiting

Authentication endpoints are rate-limited:

```php
Route::middleware('throttle:5,1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});
```

### Password Requirements

Enforced in `RegisterRequest` and `UpdatePasswordRequest`:

- Minimum 8 characters
- Must be confirmed

### Token Security

- Tokens are hashed in database (only plaintext during creation)
- Use HTTPS in production
- Set token expiration in `config/sanctum.php`:
    ```php
    'expiration' => 60 * 24 * 30, // 30 days
    ```

### CORS Configuration

API allows cross-origin requests from trusted domains:

```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL')],
```

## Common Patterns

### Getting Authenticated User

```php
// In controller
$user = $request->user();
$userId = auth()->id();

// In model/service
$user = auth()->user();
```

### Checking Roles

```php
// In controller
if ($request->user()->role !== 'vendor') {
    return response()->json(['message' => 'Unauthorized'], 403);
}

// In Blade
@auth
    @if(auth()->user()->isAdmin())
        <!-- Admin content -->
    @endif
@endauth
```

### Multi-role Access

```php
$allowedRoles = ['admin', 'super_admin'];
if (!in_array($user->role, $allowedRoles)) {
    abort(403);
}
```

## Testing Authentication

### Feature Tests

```php
// tests/Feature/AuthTest.php

public function test_user_can_register(): void
{
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+233123456789',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['user', 'token']]);
}

public function test_user_can_login(): void
{
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
            ->assertJsonStructure(['token']);
}
```

### Testing Authenticated Routes

```php
use Laravel\Sanctum\Sanctum;

public function test_authenticated_user_can_access_profile(): void
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');
    $response->assertOk();
}
```

---

This authentication system provides a secure, flexible foundation for the multi-role platform. The dual authentication strategy supports both API consumers and web dashboard users effectively.
