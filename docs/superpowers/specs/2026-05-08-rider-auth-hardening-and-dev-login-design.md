# Rider Auth Hardening & Super-Admin Dev Login — Design

**Date:** 2026-05-08
**Status:** Design (pre-implementation)
**Repos affected:** `surprise_moi_backend` (primary), `surprise_ride` (no code changes; smoke test only)
**Live API:** `https://dashboard.surprisemoi.com/api/rider/v1`

## 1. Problem

The Surprise Moi rider Flutter app has a complete login flow wired end-to-end: login screen → `AuthBloc` → repository POST `/auth/login` → token persisted to secure storage → router redirects on `RiderStatus`. The backend has working Sanctum-based rider auth (separate `riders` table, `auth:rider` guard).

But there are no rider records yet — the platform hasn't onboarded riders, and won't until later. Meanwhile, super-admins on the platform (xylaray37@gmail.com etc.) need to be able to log in to the rider app to view and test it during development.

In the same pass, four real auth gaps in the existing rider auth must be closed:

1. `/auth/login` has no rate limiting (brute-force vector).
2. `/auth/otp/send` has no rate limiting (SMS bombing → real money cost).
3. Suspended/rejected riders are issued tokens at login; they're only blocked client-side by the router redirect, not at the auth source.
4. `forgotPassword` is a stub that returns success without sending email; `resetPassword` accepts any token without validation and never updates the password.

## 2. Goals

- Super-admin in the `users` table can log in to the rider app via the existing `/api/rider/v1/auth/login` endpoint with their existing email + password.
- Existing super-admin login at `/api/v1/auth/login` (main API), Inertia web dashboard, and any other consumer remains untouched.
- Rate limiting on login and OTP send.
- Suspended/rejected riders blocked at login with explicit messages.
- Real password-reset flow via Laravel's password broker.
- All changes covered by feature tests.

## 3. Non-goals

- **No Flutter code changes.** The base URL stays hardcoded to live; the app is unchanged.
- Not implementing email verification flows for riders (out of scope for this pass).
- Not migrating shadow riders to "real" riders later — that is operational, not part of this code.
- Not implementing token expiry / refresh tokens (Sanctum personal-access tokens remain non-expiring; would be a separate hardening pass).
- Not building a dev UI for managing shadow riders.

## 4. Architecture overview

All work is in the Laravel backend. Five units of change:

1. **Migration** — add nullable `user_id` foreign key to `riders` (links shadow riders to admin User); add `rider_password_reset_tokens` table.
2. **Models** — `Rider` implements `CanResetPassword`; `belongsTo(User::class)` relation.
3. **`AuthController@login`** — primary rider lookup, then env-gated super-admin fallback that idempotently provisions a shadow rider and issues a Sanctum rider token.
4. **`AuthController@forgotPassword` / `@resetPassword`** — replace stubs with real password-broker logic; revoke all rider tokens on successful reset.
5. **Routes + RateLimiter** — apply `throttle` middleware to login and OTP send; named limiters keyed by email/IP and phone.

Flutter app is verified unchanged via smoke test only.

## 5. Detailed design

### 5.1 Database changes

**Migration `add_user_id_to_riders_table`:**
```php
Schema::table('riders', function (Blueprint $table) {
    $table->foreignId('user_id')
        ->nullable()
        ->after('id')
        ->constrained('users')
        ->nullOnDelete();
    $table->index('user_id');
});
```
Nullable so real riders (no admin link) coexist with shadow riders. `nullOnDelete` so deleting an admin doesn't cascade-delete the rider record (preserves earnings/delivery history).

**Migration `create_rider_password_reset_tokens_table`:**
```php
Schema::create('rider_password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
```
Mirrors Laravel's default `password_reset_tokens` schema. Separate table so rider resets can't collide with user resets.

### 5.2 Model changes

**`App\Models\Rider`:**
- Add `'user_id'` to `$fillable`.
- Implement `Illuminate\Contracts\Auth\CanResetPassword`:
  ```php
  public function getEmailForPasswordReset(): string { return $this->email; }
  public function sendPasswordResetNotification($token): void {
      $this->notify(new RiderResetPasswordNotification($token));
  }
  ```
- Add `belongsTo` relation:
  ```php
  public function adminUser(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
  ```
- Add helper: `public function isShadowRider(): bool { return ! is_null($this->user_id); }`.

### 5.3 Login flow

**`POST /api/rider/v1/auth/login`** with `{email|phone, password}`:

```
1. Validate via LoginRequest (existing).
2. Look up Rider where email=? OR phone=? (existing).
3. If Rider found:
   a. Hash::check fails → return 401 "Invalid credentials."
   b. Hash::check passes:
      - status = 'suspended' → 403 "Your account has been suspended. Contact support."
      - status = 'rejected'  → 403 "Your application was rejected. Contact support for details."
      - else → issue Sanctum token, return 200 (existing payload shape: { rider, token, token_type }).
4. If Rider NOT found:
   a. config('rider.admin_login_enabled') !== true → return 401 "Invalid credentials."
   b. Look up User where email=? AND role IN ('super_admin', 'admin').
      - Not found OR Hash::check fails → 401 "Invalid credentials." (uniform message; no enumeration).
      - Found + Hash::check passes → call ProvisionShadowRiderAction(admin) → issue token on shadow rider → return 200.
```

Phone-based login (no email) skips the admin fallback path — admin fallback is email-only.

**Uniform error message**: any failure path before successful auth returns the exact same `"Invalid credentials."` body and 401 status, so an attacker cannot enumerate which emails belong to riders vs. admins vs. neither.

### 5.4 Shadow-rider provisioning

`App\Actions\Rider\ProvisionShadowRiderAction` (single-method invokable class):

```php
public function __invoke(User $admin): Rider
{
    return Rider::firstOrCreate(
        ['user_id' => $admin->id],
        [
            'name'             => $admin->name,
            'email'            => $admin->email,
            'phone'            => $admin->phone ?? "admin-{$admin->id}",
            'password'         => Hash::make(Str::random(40)),
            'vehicle_category' => 'motorbike',
            'status'           => 'approved',
            'is_active'        => true,
        ]
    );
}
```

Notes:
- `firstOrCreate` keyed on `user_id` makes this idempotent — repeated logins reuse the same rider row, preserving any history (deliveries, earnings) accrued during dev testing.
- `phone` falls back to `admin-{id}` because `users.phone` may be null and `riders.phone` has a unique constraint. `riders.phone` is `string`, so a non-numeric value is schema-valid.
- `password` is a random hash never used. Shadow-rider re-auth always goes through the User table; the shadow rider's own `Hash::check` path is never exercised because `firstOrCreate` returns the existing record before we'd reach step 3 of §5.3 on subsequent logins. (To be exact: on subsequent logins step 2 finds the rider via email; step 3a fails Hash::check; we'd 401. To prevent this, step 4's admin-fallback must run BEFORE step 3, OR we must recognize shadow riders early. See §5.5.)
- `status = 'approved'` — admin reaches dashboard immediately, bypassing onboarding.

### 5.5 Login flow correction (shadow-rider re-auth)

The naïve flow in §5.3 has a subtle bug: on the second login, the admin's email matches an existing (shadow) rider row, so step 3 runs, `Hash::check` fails against the random password, and we return 401. The admin-fallback in step 4 never runs because step 4 is gated on `Rider not found`.

**Fix:** before returning 401 in step 3a, check if the rider is a shadow rider (`! is_null($rider->user_id)`). If yes, bypass the rider Hash::check and re-auth via the linked User:

```
3. If Rider found:
   a. If $rider->user_id is set (shadow rider):
      - Look up User by id = $rider->user_id, role IN ('super_admin', 'admin').
      - Hash::check($request->password, $user->password):
        - passes AND config('rider.admin_login_enabled') === true → issue token on this rider, return 200.
        - else → 401 "Invalid credentials."
   b. If $rider->user_id is null (real rider):
      - Hash::check($request->password, $rider->password):
        - fails → 401 "Invalid credentials."
        - passes → suspended/rejected gate → issue token, return 200.
4. If Rider NOT found:
   - admin fallback as in §5.3 step 4.
```

This makes the auth source for shadow riders always the User table, which is the desired property (admin password rotation in the dashboard immediately invalidates rider-app access for the same identity).

**Disabling `admin_login_enabled` in production:** existing shadow riders cannot re-auth (step 3a returns 401). They are inert until the flag flips back on or until the shadow rows are deleted.

### 5.6 Suspended/rejected gate

In step 3b after Hash::check passes:

```php
if ($rider->isSuspended()) {
    return response()->json([
        'success' => false,
        'message' => 'Your account has been suspended. Please contact support.',
    ], 403);
}
if ($rider->isRejected()) {
    return response()->json([
        'success' => false,
        'message' => 'Your application was rejected. Please contact support for details.',
    ], 403);
}
```

`pending` and `under_review` riders are still allowed to log in — they need to access onboarding.

`is_active = false` is also blocked at login with a generic 403 (admin can deactivate without changing status).

### 5.7 Rate limiting

In `App\Providers\AppServiceProvider::boot()` (or a new `RouteServiceProvider` block):

```php
RateLimiter::for('rider-login', function (Request $request) {
    $email = (string) $request->input('email', '');
    return [
        Limit::perMinute(5)->by($email . '|' . $request->ip()),
        Limit::perMinute(20)->by($request->ip()), // global per-IP cap across all login attempts
    ];
});

RateLimiter::for('rider-otp-send', function (Request $request) {
    $phone = (string) $request->input('phone', '');
    return Limit::perMinute(3)->by($phone ?: $request->ip());
});
```

In `routes/api_rider.php`:
```php
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:rider-login');
Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:rider-otp-send');
```

429 responses include the standard `Retry-After` header. The Flutter `ErrorInterceptor` already maps 429 → `RateLimitException`, so no client change needed.

### 5.8 Forgot/reset password

**Config (`config/auth.php`):**
```php
'passwords' => [
    'users' => [ /* unchanged */ ],
    'riders' => [
        'provider' => 'riders',
        'table'    => 'rider_password_reset_tokens',
        'expire'   => 60,
        'throttle' => 60,
    ],
],
```

**`AuthController@forgotPassword`:**
```php
public function forgotPassword(Request $request): JsonResponse
{
    $request->validate(['email' => 'required|email']);

    // Block password reset for shadow riders — they re-auth via User table.
    $rider = Rider::where('email', $request->email)->first();
    if ($rider && $rider->isShadowRider()) {
        return response()->json([
            'success' => false,
            'message' => 'This account uses dashboard credentials. Reset your password in the admin dashboard.',
        ], 422);
    }

    $status = Password::broker('riders')->sendResetLink(['email' => $request->email]);

    // Uniform success response — do not enumerate accounts.
    return response()->json([
        'success' => true,
        'message' => 'If an account exists with this email, a reset link has been sent.',
    ]);
}
```

**`AuthController@resetPassword`:**
```php
public function resetPassword(Request $request): JsonResponse
{
    $request->validate([
        'email'    => ['required', 'email'],
        'token'    => ['required', 'string'],
        'password' => ['required', 'string', 'confirmed', Password::min(8)],
    ]);

    $status = Password::broker('riders')->reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (Rider $rider, string $password) {
            $rider->forceFill(['password' => Hash::make($password)])->save();
            $rider->tokens()->delete(); // revoke all device tokens — force re-login everywhere
            event(new PasswordReset($rider));
        }
    );

    if ($status !== Password::PASSWORD_RESET) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired reset token.',
        ], 422);
    }

    return response()->json([
        'success' => true,
        'message' => 'Password reset successfully. Please log in with your new password.',
    ]);
}
```

**Notification `App\Notifications\RiderResetPasswordNotification`:**
- Mailable.
- Reset link is a deep link: `surprisemoi-rider://reset-password?token={$token}&email={$email}`.
- Subject: "Reset your Surprise Moi rider password".
- Email body includes the link, expiry note (60 min), and a "didn't request this" disclaimer.

A web fallback URL (`https://dashboard.surprisemoi.com/rider/reset-password?...`) is included in the email body so users on devices without the app installed see something sensible. The web page does not need to exist for this pass — link is informational. This keeps the spec scoped to the API.

### 5.9 Configuration

New file `config/rider.php`:
```php
return [
    'admin_login_enabled' => env('RIDER_ADMIN_LOGIN_ENABLED', false),
];
```

Default `false`. Live server flips to `true` via `.env` after deploy. Production is intentionally fail-safe — forgetting to set the env var means admin fallback is off.

## 6. Error handling

Errors map to existing Flutter `ErrorInterceptor` cases:

| Scenario | Status | Flutter exception |
|---|---|---|
| Invalid credentials (any reason) | 401 | `UnauthorizedException` |
| Suspended | 403 | `ForbiddenException` (with message) |
| Rejected | 403 | `ForbiddenException` (with message) |
| Inactive | 403 | `ForbiddenException` (generic) |
| Validation (missing fields, bad email) | 422 | `ValidationException` |
| Rate limited | 429 | `RateLimitException` |
| Reset token invalid/expired | 422 | `ValidationException` |

Existing Flutter `AuthBloc` already handles all of these via `on ApiException` and `on ValidationException` paths.

## 7. Testing

New / updated feature tests in `tests/Feature/Api/Rider/V1/AuthControllerTest.php`:

| # | Test | Expected |
|---|---|---|
| 1 | Real rider, valid creds | 200, returns rider + token |
| 2 | Real rider, wrong password | 401, "Invalid credentials." |
| 3 | Real rider, status=suspended | 403, suspension message |
| 4 | Real rider, status=rejected | 403, rejection message |
| 5 | Real rider, status=pending, valid creds | 200, returns token (must reach onboarding) |
| 6 | Real rider, is_active=false | 403, generic deactivated message |
| 7 | Admin fallback DISABLED, super-admin creds | 401, "Invalid credentials." |
| 8 | Admin fallback ENABLED, super-admin first login | 200, shadow rider provisioned with status=approved, user_id set |
| 9 | Admin fallback ENABLED, super-admin second login | 200, same shadow rider reused (idempotent) |
| 10 | Admin fallback ENABLED, non-admin user (role=customer) | 401, "Invalid credentials." |
| 11 | Admin fallback ENABLED, super-admin wrong password | 401, "Invalid credentials." |
| 12 | Admin fallback ENABLED, then DISABLED, second login attempt | 401, "Invalid credentials." (shadow rider exists but inert) |
| 13 | Login throttle: 6 attempts in 1 min | 6th returns 429 |
| 14 | OTP send throttle: 4 sends in 1 min | 4th returns 429 |
| 15 | Forgot password, real rider exists | 200, asserts `RiderResetPasswordNotification` dispatched |
| 16 | Forgot password, no rider with email | 200, uniform message, no notification dispatched |
| 17 | Forgot password, shadow rider | 422, dashboard-credentials message |
| 18 | Reset password, valid token | 200, password updated, all rider tokens deleted |
| 19 | Reset password, expired token | 422 |
| 20 | Reset password, mismatched confirmation | 422 |

Tests use the `RefreshDatabase` trait. `Notification::fake()` for tests 15–17. `Cache::flush()` in `setUp` to reset rate-limiter state between tests.

`UserFactory` may need a `superAdmin()` state (`['role' => 'super_admin']`) if it doesn't already exist.
`RiderFactory` should support `shadow($user)` and `withStatus($status)` states for clarity.

## 8. Deploy & smoke test

1. Merge PR to `main`.
2. On live server (`/var/www/surprise_moi`):
   - `git pull origin main`
   - `php artisan migrate`
   - Set `.env`: `RIDER_ADMIN_LOGIN_ENABLED=true`
   - `php artisan config:cache`
   - `php artisan route:cache`
3. From the Flutter app on a phone or emulator: log in with `xylaray37@gmail.com` / `Gilash@123`.
4. Verify: lands on dashboard (status=approved). Bottom-nav reachable. `/api/rider/v1/profile` returns the shadow rider.
5. Log out → log in again → still works (idempotent).
6. From Postman or curl: `POST /api/rider/v1/auth/login` 6 times in a minute with bad creds → 6th returns 429.

## 9. Rollback plan

If anything is wrong post-deploy:
1. Set `.env`: `RIDER_ADMIN_LOGIN_ENABLED=false` → `php artisan config:cache`. Admin fallback off in seconds without code rollback. Real rider login (when riders exist) and other hardening remain in effect.
2. If a deeper issue: `git revert <merge-commit>` → redeploy. Migrations are forward-compatible (nullable column, new table) — rolling back code without rolling back migrations is safe.
3. To wipe shadow riders later: `DELETE FROM riders WHERE user_id IS NOT NULL;` (after confirming no real riders share that property).

## 10. Open questions

None at design time. All decisions are made; implementation plan can proceed.
