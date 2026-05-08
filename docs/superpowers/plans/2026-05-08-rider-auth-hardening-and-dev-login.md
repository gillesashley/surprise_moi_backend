# Rider Auth Hardening & Super-Admin Dev Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow super-admins in the `users` table to log in to the rider app via existing `/api/rider/v1/auth/login` (gated by an env flag, idempotently provisions a "shadow rider"); and harden rider auth with login + OTP rate limiting, suspended/rejected gating at the auth source, and a real password-reset flow via Laravel's password broker.

**Architecture:** Single Laravel feature branch. Five units of change: (1) two migrations (FK + reset tokens), (2) `config/rider.php` + auth.php broker config, (3) `Rider` model becomes `CanResetPassword` and gains `belongsTo(User)`, (4) refactored `AuthController` with shadow-rider provisioning and password broker, (5) named rate limiters applied via `throttle:` middleware. Production stays fail-safe — `RIDER_ADMIN_LOGIN_ENABLED` defaults to `false`.

**Tech Stack:** Laravel 12, PHP 8.2, Sanctum 4, PHPUnit 11. Working dir: `C:\dev\surprise_moi_backend`. Feature branch: `feat/rider-auth-hardening-and-dev-login` (already created).

**Spec:** `docs/superpowers/specs/2026-05-08-rider-auth-hardening-and-dev-login-design.md`

---

## File Map

**Created:**
- `database/migrations/2026_05_08_100000_add_user_id_to_riders_table.php`
- `database/migrations/2026_05_08_100100_create_rider_password_reset_tokens_table.php`
- `config/rider.php`
- `app/Actions/Rider/ProvisionShadowRiderAction.php`
- `app/Notifications/RiderResetPasswordNotification.php`
- `tests/Feature/Api/Rider/V1/AuthControllerTest.php`
- `tests/Unit/Actions/Rider/ProvisionShadowRiderActionTest.php`

**Modified:**
- `app/Models/Rider.php` — `CanResetPassword` interface, `adminUser()` relation, `isShadowRider()`, `user_id` in `$fillable`
- `app/Http/Controllers/Api/Rider/V1/AuthController.php` — full login refactor + real forgot/reset
- `routes/api_rider.php` — apply `throttle:rider-login` and `throttle:rider-otp-send`
- `app/Providers/AppServiceProvider.php` — register named rate limiters in `boot()`
- `config/auth.php` — add `riders` password broker
- `database/factories/UserFactory.php` — `superAdmin()` state
- `database/factories/RiderFactory.php` — `shadowOf(User)` state
- `.env.example` — `RIDER_ADMIN_LOGIN_ENABLED=false`

---

## Conventions

- Run all PHP commands inside Docker (`docker compose exec app ...`) per backend `CLAUDE.md`. If your local setup uses bare `php`, substitute. **The plan uses `docker compose exec app` form.**
- Per backend `CLAUDE.md`: run `vendor/bin/pint --dirty --format agent` before each commit.
- Every test must be programmatically executed and pass before its commit.
- Commit messages follow conventional prefixes (`feat`, `fix`, `chore`, `test`, `refactor`).

---

## Task 1: Foundation — factories, base test file

**Files:**
- Modify: `database/factories/UserFactory.php`
- Modify: `database/factories/RiderFactory.php`
- Create: `tests/Feature/Api/Rider/V1/AuthControllerTest.php`

- [ ] **Step 1: Add `superAdmin()` state to `UserFactory`**

Add this method to `database/factories/UserFactory.php` directly after the existing `admin()` method (around line 106):

```php
    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
        ]);
    }
```

- [ ] **Step 2: Add `shadowOf()` state to `RiderFactory`**

Add this method to `database/factories/RiderFactory.php` at the end of the class (after `withDocuments`):

```php
    /**
     * Indicate that this is a shadow rider linked to an admin User.
     * Used in tests of the super-admin dev-login fallback.
     */
    public function shadowOf(\App\Models\User $admin): static
    {
        return $this->state(fn () => [
            'user_id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone ?? "admin-{$admin->id}",
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
            'status' => 'approved',
            'is_active' => true,
        ]);
    }
```

- [ ] **Step 3: Create the rider auth test file scaffold**

Create `tests/Feature/Api/Rider/V1/AuthControllerTest.php`:

```php
<?php

namespace Tests\Feature\Api\Rider\V1;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset rate-limiter state between tests so throttle counters don't bleed.
        Cache::flush();
    }
}
```

- [ ] **Step 4: Verify PHPUnit picks up the empty class**

Run: `docker compose exec app php artisan test --compact --filter=AuthControllerTest`
Expected: `OK (0 tests)` or "No tests executed". The class loads without parse errors.

- [ ] **Step 5: Format and commit**

```bash
cd C:/dev/surprise_moi_backend
docker compose exec app vendor/bin/pint --dirty --format agent
git add database/factories/UserFactory.php database/factories/RiderFactory.php tests/Feature/Api/Rider/V1/AuthControllerTest.php
git commit -m "test(rider-auth): add factory states and test file scaffold

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Migration — add `user_id` to riders

**Files:**
- Create: `database/migrations/2026_05_08_100000_add_user_id_to_riders_table.php`

- [ ] **Step 1: Generate the migration**

Run:
```bash
docker compose exec app php artisan make:migration add_user_id_to_riders_table --table=riders --no-interaction
```

This creates a file with today's timestamp. Rename or use the file Laravel created. Replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('user_id', 'riders_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('riders_user_id_index');
            $table->dropColumn('user_id');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `docker compose exec app php artisan migrate --no-interaction`
Expected: shows the new migration applied. No errors.

- [ ] **Step 3: Verify schema**

Run:
```bash
docker compose exec app php artisan tinker --execute="dump(\Illuminate\Support\Facades\Schema::hasColumn('riders', 'user_id'));"
```
Expected output contains `true`.

- [ ] **Step 4: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add database/migrations/
git commit -m "feat(rider-auth): add nullable user_id FK on riders for shadow riders

Links shadow rider records to the admin User they were provisioned for.
Nullable so real riders coexist. nullOnDelete preserves rider history.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Migration — create `rider_password_reset_tokens` table

**Files:**
- Create: `database/migrations/2026_05_08_100100_create_rider_password_reset_tokens_table.php`

- [ ] **Step 1: Generate the migration**

Run:
```bash
docker compose exec app php artisan make:migration create_rider_password_reset_tokens_table --no-interaction
```

Replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_password_reset_tokens');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `docker compose exec app php artisan migrate --no-interaction`
Expected: shows the new migration applied.

- [ ] **Step 3: Verify table exists**

Run:
```bash
docker compose exec app php artisan tinker --execute="dump(\Illuminate\Support\Facades\Schema::hasTable('rider_password_reset_tokens'));"
```
Expected output contains `true`.

- [ ] **Step 4: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add database/migrations/
git commit -m "feat(rider-auth): create rider_password_reset_tokens table

Separate from password_reset_tokens so rider resets cannot collide
with user (admin/customer/vendor) resets.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Configuration — `config/rider.php`, `auth.php` broker, `.env.example`

**Files:**
- Create: `config/rider.php`
- Modify: `config/auth.php`
- Modify: `.env.example`

- [ ] **Step 1: Create `config/rider.php`**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Login Fallback
    |--------------------------------------------------------------------------
    |
    | When true, /api/rider/v1/auth/login accepts credentials of users with
    | role super_admin or admin and idempotently provisions a "shadow rider"
    | record so the admin can log in to the rider app for development and
    | smoke testing. Defaults to false; production should keep it false
    | unless explicitly enabled.
    |
    */
    'admin_login_enabled' => env('RIDER_ADMIN_LOGIN_ENABLED', false),
];
```

- [ ] **Step 2: Add `riders` password broker to `config/auth.php`**

Find the `'passwords' => [ ... ]` block (around line 103) and add a `'riders'` key inside it. The block becomes:

```php
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'riders' => [
            'provider' => 'riders',
            'table' => 'rider_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
```

- [ ] **Step 3: Add the env var to `.env.example`**

Append to `.env.example`:

```
RIDER_ADMIN_LOGIN_ENABLED=false
```

- [ ] **Step 4: Clear and reload config**

Run: `docker compose exec app php artisan config:clear`
Expected: "Configuration cache cleared successfully."

- [ ] **Step 5: Verify config loads**

Run:
```bash
docker compose exec app php artisan tinker --execute="dump(config('rider.admin_login_enabled'));dump(config('auth.passwords.riders'));"
```
Expected: `false`, then an array with `provider => "riders"`, `table => "rider_password_reset_tokens"`.

- [ ] **Step 6: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add config/rider.php config/auth.php .env.example
git commit -m "feat(rider-auth): add rider config and password broker

config/rider.php exposes admin_login_enabled (default off).
auth.php registers a riders password broker bound to the new table.
.env.example documents the new env var.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Rider model — `CanResetPassword`, relation, helper

**Files:**
- Modify: `app/Models/Rider.php`

- [ ] **Step 1: Update class declaration and `$fillable`**

Open `app/Models/Rider.php`. At the top, update imports (replace existing imports block with this):

```php
use App\Notifications\RiderResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
```

Change the class declaration:

```php
class Rider extends Authenticatable implements CanResetPassword
```

Add `'user_id'` as the first item in `$fillable`:

```php
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        // ... rest unchanged
    ];
```

- [ ] **Step 2: Add `adminUser()` relation, `isShadowRider()` helper, and `CanResetPassword` methods**

Find the section after `isRejected()` (around line 116). Insert these methods immediately after `isRejected()`:

```php
    /**
     * Whether this rider is a shadow rider linked to an admin User.
     */
    public function isShadowRider(): bool
    {
        return ! is_null($this->user_id);
    }

    /**
     * The admin User that owns this shadow rider, if any.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * CanResetPassword: email used to send the reset link.
     */
    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email;
    }

    /**
     * CanResetPassword: dispatch the rider-specific notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RiderResetPasswordNotification($token));
    }
```

(`RiderResetPasswordNotification` doesn't exist yet; we create it in Task 6. The model will compile because it's only referenced inside a method body that isn't called yet.)

- [ ] **Step 3: Verify the file parses**

Run: `docker compose exec app php artisan tinker --execute="dump((new \App\Models\Rider)->isShadowRider());"`
Expected: `false` (new instance has no user_id).

- [ ] **Step 4: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Models/Rider.php
git commit -m "feat(rider-auth): rider model implements CanResetPassword and links to User

- adminUser() BelongsTo for shadow riders
- isShadowRider() helper
- getEmailForPasswordReset / sendPasswordResetNotification for the
  Laravel password broker

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `RiderResetPasswordNotification`

**Files:**
- Create: `app/Notifications/RiderResetPasswordNotification.php`

- [ ] **Step 1: Generate the notification**

Run: `docker compose exec app php artisan make:notification RiderResetPasswordNotification --no-interaction`

- [ ] **Step 2: Replace the generated file with the implementation**

Replace the entire contents of `app/Notifications/RiderResetPasswordNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $deepLink = "surprisemoi-rider://reset-password?token={$this->token}&email=".urlencode($email);
        $webFallback = config('app.url').'/rider/reset-password?token='.$this->token.'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Reset your Surprise Moi rider password')
            ->greeting("Hello {$notifiable->name},")
            ->line('We received a request to reset your Surprise Moi rider password.')
            ->action('Reset Password', $deepLink)
            ->line('This link will expire in 60 minutes.')
            ->line("If the button does not open the app, copy this link: {$webFallback}")
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('— The Surprise Moi Team');
    }
}
```

- [ ] **Step 3: Verify it loads**

Run: `docker compose exec app php artisan tinker --execute="dump(new \App\Notifications\RiderResetPasswordNotification('abc123'));"`
Expected: dump shows the notification instance with `token: "abc123"`.

- [ ] **Step 4: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Notifications/RiderResetPasswordNotification.php
git commit -m "feat(rider-auth): add RiderResetPasswordNotification mailable

Sends a deep-link reset URL plus a web fallback. 60-minute expiry
matches config/auth.php passwords.riders.expire.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: `ProvisionShadowRiderAction` (TDD)

**Files:**
- Create: `tests/Unit/Actions/Rider/ProvisionShadowRiderActionTest.php`
- Create: `app/Actions/Rider/ProvisionShadowRiderAction.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Actions/Rider/ProvisionShadowRiderActionTest.php`:

```php
<?php

namespace Tests\Unit\Actions\Rider;

use App\Actions\Rider\ProvisionShadowRiderAction;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionShadowRiderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_shadow_rider_for_a_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'name' => 'Ash Admin',
            'email' => 'ash@example.com',
            'phone' => '+233200000001',
        ]);

        $rider = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame($admin->id, $rider->user_id);
        $this->assertSame('Ash Admin', $rider->name);
        $this->assertSame('ash@example.com', $rider->email);
        $this->assertSame('+233200000001', $rider->phone);
        $this->assertSame('approved', $rider->status);
        $this->assertSame('motorbike', $rider->vehicle_category);
        $this->assertTrue((bool) $rider->is_active);
    }

    public function test_it_synthesizes_phone_when_admin_has_none(): void
    {
        $admin = User::factory()->superAdmin()->create(['phone' => null]);

        $rider = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame("admin-{$admin->id}", $rider->phone);
    }

    public function test_it_is_idempotent(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $first = (new ProvisionShadowRiderAction)($admin);
        $second = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Rider::where('user_id', $admin->id)->count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec app php artisan test --compact --filter=ProvisionShadowRiderActionTest`
Expected: FAIL with "Class App\Actions\Rider\ProvisionShadowRiderAction not found".

- [ ] **Step 3: Create the action**

Create `app/Actions/Rider/ProvisionShadowRiderAction.php`:

```php
<?php

namespace App\Actions\Rider;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionShadowRiderAction
{
    /**
     * Idempotently provision (or fetch) a shadow Rider record for the given admin User.
     *
     * Keyed on user_id, so repeat calls return the same Rider — preserving any
     * delivery/earnings history accrued during dev testing.
     */
    public function __invoke(User $admin): Rider
    {
        return Rider::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone ?? "admin-{$admin->id}",
                'password' => Hash::make(Str::random(40)),
                'vehicle_category' => 'motorbike',
                'status' => 'approved',
                'is_active' => true,
            ]
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec app php artisan test --compact --filter=ProvisionShadowRiderActionTest`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add tests/Unit/Actions/Rider/ProvisionShadowRiderActionTest.php app/Actions/Rider/ProvisionShadowRiderAction.php
git commit -m "feat(rider-auth): add ProvisionShadowRiderAction

Idempotently provisions a shadow Rider for a super-admin User
keyed on user_id. Preserves any rider history across repeat calls.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Refactor `AuthController@login` (TDD)

**Files:**
- Modify: `tests/Feature/Api/Rider/V1/AuthControllerTest.php`
- Modify: `app/Http/Controllers/Api/Rider/V1/AuthController.php`

- [ ] **Step 1: Write the failing login tests**

Append the following methods to `tests/Feature/Api/Rider/V1/AuthControllerTest.php` (inside the class, before the closing brace):

```php
    // ---------- Existing rider login ----------

    public function test_real_rider_with_valid_credentials_can_login(): void
    {
        $rider = Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['rider' => ['id', 'email'], 'token', 'token_type'],
            ])
            ->assertJsonPath('data.rider.id', $rider->id);
    }

    public function test_real_rider_wrong_password_returns_401(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);
    }

    public function test_suspended_rider_is_blocked_with_403(): void
    {
        Rider::factory()->suspended()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Your account has been suspended. Please contact support.']);
    }

    public function test_rejected_rider_is_blocked_with_403(): void
    {
        Rider::factory()->rejected()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Your application was rejected. Please contact support for details.']);
    }

    public function test_inactive_rider_is_blocked_with_403(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_pending_rider_can_still_login(): void
    {
        Rider::factory()->pending()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk();
    }

    // ---------- Super-admin fallback ----------

    public function test_admin_fallback_disabled_by_default_admin_creds_return_401(): void
    {
        config(['rider.admin_login_enabled' => false]);

        User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);

        $this->assertDatabaseMissing('riders', ['email' => 'admin@example.com']);
    }

    public function test_admin_fallback_enabled_super_admin_first_login_provisions_shadow_rider(): void
    {
        config(['rider.admin_login_enabled' => true]);

        $admin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
            'phone' => '+233200000001',
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['rider', 'token', 'token_type']]);

        $this->assertDatabaseHas('riders', [
            'user_id' => $admin->id,
            'email' => 'admin@example.com',
            'status' => 'approved',
            'vehicle_category' => 'motorbike',
        ]);
    }

    public function test_admin_fallback_second_login_reuses_shadow_rider(): void
    {
        config(['rider.admin_login_enabled' => true]);

        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $payload = ['email' => 'admin@example.com', 'password' => 'AdminPass1!'];
        $this->postJson('/api/rider/v1/auth/login', $payload)->assertOk();
        $this->postJson('/api/rider/v1/auth/login', $payload)->assertOk();

        $this->assertSame(1, Rider::where('user_id', $admin->id)->count());
    }

    public function test_admin_fallback_non_admin_user_returns_401(): void
    {
        config(['rider.admin_login_enabled' => true]);

        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('CustomerPass1!'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'CustomerPass1!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials.']);
    }

    public function test_admin_fallback_super_admin_wrong_password_returns_401(): void
    {
        config(['rider.admin_login_enabled' => true]);

        User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('riders', ['email' => 'admin@example.com']);
    }

    public function test_shadow_rider_inert_when_flag_disabled(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass1!'),
        ]);
        Rider::factory()->shadowOf($admin)->create();

        config(['rider.admin_login_enabled' => false]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass1!',
        ]);

        $response->assertStatus(401);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test --compact --filter=AuthControllerTest`
Expected: FAIL on the suspended/rejected/inactive/admin-fallback tests. Existing valid-credentials test may already pass (existing implementation supports it).

- [ ] **Step 3: Refactor `AuthController@login`**

Open `app/Http/Controllers/Api/Rider/V1/AuthController.php`. Replace the entire `login` method (and update the imports at the top of the file).

Replace the existing `use` block with:

```php
use App\Actions\Rider\ProvisionShadowRiderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\LoginRequest;
use App\Http\Requests\Api\Rider\V1\RegisterRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use App\Models\Rider;
use App\Models\User;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
```

Replace the entire `login` method with:

```php
    /**
     * Login a rider with email or phone. When config('rider.admin_login_enabled')
     * is true, also accepts super-admin / admin credentials from the users table
     * and idempotently provisions a shadow rider.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $phone = $request->input('phone');
        $password = $request->input('password');

        $rider = $email
            ? Rider::where('email', $email)->first()
            : Rider::where('phone', $phone)->first();

        if ($rider) {
            return $this->authenticateExistingRider($rider, $password);
        }

        if ($email && config('rider.admin_login_enabled')) {
            $admin = User::where('email', $email)
                ->whereIn('role', ['super_admin', 'admin'])
                ->first();

            if ($admin && Hash::check($password, $admin->password)) {
                $shadowRider = (new ProvisionShadowRiderAction)($admin);
                return $this->issueLoginResponse($shadowRider, 'Login successful.');
            }
        }

        return $this->invalidCredentialsResponse();
    }

    /**
     * Handle a Rider row that matched the email/phone lookup.
     * Splits real-rider auth (Hash::check on rider.password) from shadow-rider
     * auth (Hash::check on the linked admin's password).
     */
    protected function authenticateExistingRider(Rider $rider, string $password): JsonResponse
    {
        if ($rider->isShadowRider()) {
            if (! config('rider.admin_login_enabled')) {
                return $this->invalidCredentialsResponse();
            }

            $admin = User::where('id', $rider->user_id)
                ->whereIn('role', ['super_admin', 'admin'])
                ->first();

            if (! $admin || ! Hash::check($password, $admin->password)) {
                return $this->invalidCredentialsResponse();
            }

            return $this->issueLoginResponse($rider, 'Login successful.');
        }

        if (! Hash::check($password, $rider->password)) {
            return $this->invalidCredentialsResponse();
        }

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

        if (! $rider->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently deactivated. Please contact support.',
            ], 403);
        }

        return $this->issueLoginResponse($rider, 'Login successful.');
    }

    protected function issueLoginResponse(Rider $rider, string $message): JsonResponse
    {
        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    protected function invalidCredentialsResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials.',
        ], 401);
    }
```

- [ ] **Step 4: Run the tests**

Run: `docker compose exec app php artisan test --compact --filter=AuthControllerTest`
Expected: All 11 login tests pass.

- [ ] **Step 5: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Rider/V1/AuthController.php tests/Feature/Api/Rider/V1/AuthControllerTest.php
git commit -m "feat(rider-auth): refactor login with shadow-rider fallback and status gate

- Real riders: Hash::check + suspended/rejected/inactive 403 gate.
- Shadow riders: re-auth via linked admin's User row, gated by
  rider.admin_login_enabled.
- Unknown email + flag enabled: super_admin/admin fallback that
  provisions a shadow rider via ProvisionShadowRiderAction.
- All failure paths return uniform 401 \"Invalid credentials.\"

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Real `forgotPassword` (TDD)

**Files:**
- Modify: `tests/Feature/Api/Rider/V1/AuthControllerTest.php`
- Modify: `app/Http/Controllers/Api/Rider/V1/AuthController.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Api/Rider/V1/AuthControllerTest.php` (inside the class, before its closing brace):

```php
    // ---------- Forgot password ----------

    public function test_forgot_password_for_real_rider_dispatches_notification(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $rider = Rider::factory()->approved()->create(['email' => 'rider@example.com']);

        $response = $this->postJson('/api/rider/v1/auth/forgot-password', [
            'email' => 'rider@example.com',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        \Illuminate\Support\Facades\Notification::assertSentTo(
            $rider,
            \App\Notifications\RiderResetPasswordNotification::class
        );
    }

    public function test_forgot_password_unknown_email_returns_uniform_success(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->postJson('/api/rider/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_forgot_password_for_shadow_rider_returns_422(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->superAdmin()->create();
        Rider::factory()->shadowOf($admin)->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/rider/v1/auth/forgot-password', [
            'email' => 'admin@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_forgot"`
Expected: FAIL — existing stub returns 404 for unknown emails (uniform-success test fails) and the shadow-rider check doesn't exist.

- [ ] **Step 3: Replace `forgotPassword` method**

In `app/Http/Controllers/Api/Rider/V1/AuthController.php`, replace the entire `forgotPassword` method with:

```php
    /**
     * Send password reset instructions via the riders password broker.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $rider = Rider::where('email', $request->email)->first();
        if ($rider && $rider->isShadowRider()) {
            return response()->json([
                'success' => false,
                'message' => 'This account uses dashboard credentials. Reset your password in the admin dashboard.',
            ], 422);
        }

        Password::broker('riders')->sendResetLink(['email' => $request->email]);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with this email, a reset link has been sent.',
        ]);
    }
```

- [ ] **Step 4: Run the tests**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_forgot"`
Expected: All 3 forgot-password tests pass.

- [ ] **Step 5: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Rider/V1/AuthController.php tests/Feature/Api/Rider/V1/AuthControllerTest.php
git commit -m "feat(rider-auth): real forgot-password via riders broker

Replaces the stub. Sends RiderResetPasswordNotification through
Password::broker('riders'). Returns uniform success for unknown
emails to avoid account enumeration. Shadow riders return 422
pointing to dashboard credentials.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Real `resetPassword` (TDD)

**Files:**
- Modify: `tests/Feature/Api/Rider/V1/AuthControllerTest.php`
- Modify: `app/Http/Controllers/Api/Rider/V1/AuthController.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Api/Rider/V1/AuthControllerTest.php`:

```php
    // ---------- Reset password ----------

    public function test_reset_password_with_valid_token_updates_password_and_revokes_tokens(): void
    {
        $rider = Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('OldSecret1!'),
        ]);
        // Simulate an active session by issuing a token first.
        $rider->createToken('rider-app');
        $token = \Illuminate\Support\Facades\Password::broker('riders')->createToken($rider);

        $response = $this->postJson('/api/rider/v1/auth/reset-password', [
            'email' => 'rider@example.com',
            'token' => $token,
            'password' => 'NewSecret1!',
            'password_confirmation' => 'NewSecret1!',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $rider->refresh();
        $this->assertTrue(Hash::check('NewSecret1!', $rider->password));
        $this->assertSame(0, $rider->tokens()->count());
    }

    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('OldSecret1!'),
        ]);

        $response = $this->postJson('/api/rider/v1/auth/reset-password', [
            'email' => 'rider@example.com',
            'token' => 'definitely-not-a-real-token',
            'password' => 'NewSecret1!',
            'password_confirmation' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Invalid or expired reset token.']);
    }

    public function test_reset_password_with_mismatched_confirmation_returns_422(): void
    {
        Rider::factory()->approved()->create(['email' => 'rider@example.com']);

        $response = $this->postJson('/api/rider/v1/auth/reset-password', [
            'email' => 'rider@example.com',
            'token' => 'whatever',
            'password' => 'NewSecret1!',
            'password_confirmation' => 'DifferentValue1!',
        ]);

        $response->assertStatus(422);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_reset"`
Expected: FAIL — existing stub does not actually validate or persist.

- [ ] **Step 3: Replace `resetPassword` method**

In `app/Http/Controllers/Api/Rider/V1/AuthController.php`, replace the entire `resetPassword` method with:

```php
    /**
     * Reset rider's password using the riders password broker.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('riders')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Rider $rider, string $password): void {
                $rider->forceFill(['password' => Hash::make($password)])->save();
                $rider->tokens()->delete();
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

- [ ] **Step 4: Run the tests**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_reset"`
Expected: All 3 reset-password tests pass.

- [ ] **Step 5: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Rider/V1/AuthController.php tests/Feature/Api/Rider/V1/AuthControllerTest.php
git commit -m "feat(rider-auth): real reset-password via riders broker

Validates token via Password::broker('riders'), hashes new password,
revokes all rider Sanctum tokens (force re-login on every device),
fires PasswordReset event.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Rate limiters (TDD)

**Files:**
- Modify: `tests/Feature/Api/Rider/V1/AuthControllerTest.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/api_rider.php`

- [ ] **Step 1: Write the failing rate-limit tests**

Append to `tests/Feature/Api/Rider/V1/AuthControllerTest.php`:

```php
    // ---------- Rate limiting ----------

    public function test_login_throttles_after_5_attempts_per_minute(): void
    {
        Rider::factory()->approved()->create([
            'email' => 'rider@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $payload = ['email' => 'rider@example.com', 'password' => 'WrongPassword'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/rider/v1/auth/login', $payload)
                ->assertStatus(401);
        }

        $this->postJson('/api/rider/v1/auth/login', $payload)
            ->assertStatus(429);
    }

    public function test_otp_send_throttles_after_3_attempts_per_minute(): void
    {
        Rider::factory()->approved()->create(['phone' => '+233200000099']);

        // Mock the SMS service so we don't actually send.
        $this->mock(\App\Services\KairosAfrikaSmsService::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->andReturn(['success' => true]);
        });

        $payload = ['phone' => '+233200000099'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/rider/v1/auth/otp/send', $payload)->assertOk();
        }

        $this->postJson('/api/rider/v1/auth/otp/send', $payload)
            ->assertStatus(429);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_login_throttles|AuthControllerTest::test_otp"`
Expected: FAIL — without throttle middleware, the 6th login returns 401 (not 429), and OTP send keeps succeeding.

- [ ] **Step 3: Register named limiters in `AppServiceProvider::boot()`**

Open `app/Providers/AppServiceProvider.php`. Find the existing `RateLimiter::for('treasury-transfer', ...)` block (around line 92). Insert the two new limiters immediately above it:

```php
        RateLimiter::for('rider-login', function (Request $request) {
            $email = (string) $request->input('email', '');
            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('rider-otp-send', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            return Limit::perMinute(3)->by($phone !== '' ? $phone : $request->ip());
        });

```

(`Limit`, `RateLimiter`, and `Request` are already imported in this file — verified earlier.)

- [ ] **Step 4: Apply the throttle middleware to the routes**

Open `routes/api_rider.php`. Replace these two existing lines:

```php
        Route::post('login', [AuthController::class, 'login']);
        Route::post('otp/send', [AuthController::class, 'sendOtp']);
```

with:

```php
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:rider-login');
        Route::post('otp/send', [AuthController::class, 'sendOtp'])
            ->middleware('throttle:rider-otp-send');
```

- [ ] **Step 5: Run the tests**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest::test_login_throttles|AuthControllerTest::test_otp"`
Expected: Both rate-limit tests pass. (`Cache::flush()` in the test `setUp` is what makes the limiter state isolated between tests.)

- [ ] **Step 6: Commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Providers/AppServiceProvider.php routes/api_rider.php tests/Feature/Api/Rider/V1/AuthControllerTest.php
git commit -m "feat(rider-auth): rate-limit login and OTP-send endpoints

- rider-login: 5/min per email+ip, 20/min per ip globally.
- rider-otp-send: 3/min per phone (or per ip if missing).

Returns 429 with standard Retry-After. Flutter ErrorInterceptor
already maps 429 to RateLimitException, no client change needed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Final verification, push, open PR

- [ ] **Step 1: Full rider auth test suite**

Run: `docker compose exec app php artisan test --compact --filter="AuthControllerTest|ProvisionShadowRiderActionTest"`
Expected: All tests pass — roughly 22 tests total (3 unit + 19 feature).

- [ ] **Step 2: Run the broader suite to catch regressions**

Run: `docker compose exec app php artisan test --compact`
Expected: Pre-existing failures (if any) unchanged; nothing newly broken. If any test regresses, investigate before pushing.

- [ ] **Step 3: Final Pint pass**

Run: `docker compose exec app vendor/bin/pint --dirty --format agent`
Expected: "No issues found." (We've been formatting per-commit.)

- [ ] **Step 4: Push the branch**

```bash
cd C:/dev/surprise_moi_backend
git push -u origin feat/rider-auth-hardening-and-dev-login
```

- [ ] **Step 5: Open the PR**

```bash
gh pr create \
  --title "feat(rider-auth): hardening + super-admin dev login fallback" \
  --body "$(cat <<'EOF'
## Summary
- Allow super-admins (role super_admin or admin) to log in to the rider app via existing /api/rider/v1/auth/login by idempotently provisioning a "shadow rider" linked to their User row. Gated by `RIDER_ADMIN_LOGIN_ENABLED` (default false).
- Block suspended/rejected/inactive riders at the auth source (403) instead of issuing tokens and relying on client-side redirects.
- Real password-reset flow via Laravel's password broker on a new `rider_password_reset_tokens` table; reset revokes all rider Sanctum tokens.
- Rate limiting: `/auth/login` 5/min per email+ip (and 20/min per ip), `/auth/otp/send` 3/min per phone.

Spec: `docs/superpowers/specs/2026-05-08-rider-auth-hardening-and-dev-login-design.md`

## Test plan
- [ ] CI runs the new feature + unit tests (22 tests, all passing locally).
- [ ] After merge, deploy as in the spec §8: pull, `php artisan migrate`, set `RIDER_ADMIN_LOGIN_ENABLED=true` on the live `.env`, `config:cache`.
- [ ] From Flutter app, log in with super-admin email/password — should reach the dashboard.
- [ ] Repeat login → same shadow rider, no duplicates.
- [ ] `POST /api/rider/v1/auth/login` with bad creds 6× in a minute → 6th returns 429.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 6: Report PR URL to user**

Print the URL `gh pr create` returned, then stop. The user merges the PR and runs the deploy steps from §8 of the spec when they're ready.

---

## Self-Review Notes

- **Spec coverage:** Every section of `docs/superpowers/specs/2026-05-08-...md` is implemented:
  - §5.1 migrations → Tasks 2, 3
  - §5.2 model → Task 5
  - §5.3 + §5.5 login flow → Task 8
  - §5.4 ProvisionShadowRiderAction → Task 7
  - §5.6 suspended/rejected gate → Task 8
  - §5.7 rate limiters → Task 11
  - §5.8 forgot/reset password → Tasks 9, 10
  - §5.9 config → Task 4
  - §6 error mapping → covered by uniform `invalidCredentialsResponse()` and the explicit 403/422 returns
  - §7 testing matrix — 19 of 20 spec tests are present; spec test #16 ("forgot password unknown email returns uniform success") and #17 (shadow rider 422) are merged into Task 9's three tests, which together cover both behaviors.
- **Flutter side:** intentionally untouched per the design.
- **Production safety:** `RIDER_ADMIN_LOGIN_ENABLED=false` default; rollback is a single env flip + `config:cache`.
