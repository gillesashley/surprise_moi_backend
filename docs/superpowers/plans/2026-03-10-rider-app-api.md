# Rider App API Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan.

**Goal:** Build a complete rider/delivery API module for the Surprise Moi platform enabling rider registration, delivery dispatch, live tracking, and earnings management.

**Architecture:** Separate API module at `/api/rider/v1/` with dedicated route file, controllers, and form requests. Shares models, services, and middleware with the main app. Rider model extended to support authentication via Sanctum with a custom `rider` guard.

**Tech Stack:** Laravel 12, Sanctum v4, Reverb (WebSocket), FCM push notifications, PostgreSQL

**Design Doc:** `docs/plans/2026-03-10-rider-app-api-design.md`

---

## File Structure

### Config Changes
- Modify: `config/auth.php` — add `rider` guard and provider
- Modify: `bootstrap/app.php` — register `routes/api_rider.php`

### Routes
- Create: `routes/api_rider.php`

### Migrations (6)
- Create: `database/migrations/2026_03_11_000001_update_riders_table_for_authentication.php`
- Create: `database/migrations/2026_03_11_000002_create_delivery_requests_table.php`
- Create: `database/migrations/2026_03_11_000003_create_rider_earnings_table.php`
- Create: `database/migrations/2026_03_11_000004_create_rider_withdrawal_requests_table.php`
- Create: `database/migrations/2026_03_11_000005_create_vendor_riders_table.php`
- Create: `database/migrations/2026_03_11_000006_create_rider_location_logs_table.php`

### Models (5 new + 1 modified)
- Modify: `app/Models/Rider.php`
- Create: `app/Models/DeliveryRequest.php`
- Create: `app/Models/RiderEarning.php`
- Create: `app/Models/RiderWithdrawalRequest.php`
- Create: `app/Models/VendorRider.php`
- Create: `app/Models/RiderLocationLog.php`

### Factories (6)
- Modify: `database/factories/RiderFactory.php`
- Create: `database/factories/DeliveryRequestFactory.php`
- Create: `database/factories/RiderEarningFactory.php`
- Create: `database/factories/RiderWithdrawalRequestFactory.php`
- Create: `database/factories/VendorRiderFactory.php`
- Create: `database/factories/RiderLocationLogFactory.php`

### Middleware (1)
- Create: `app/Http/Middleware/EnsureRiderApproved.php`

### Services (2)
- Create: `app/Services/RiderBalanceService.php`
- Create: `app/Services/DeliveryDispatchService.php`

### Controllers (7)
- Create: `app/Http/Controllers/Api/Rider/V1/AuthController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/OnboardingController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/DashboardController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/DeliveryController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/EarningController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/ProfileController.php`
- Create: `app/Http/Controllers/Api/Rider/V1/VendorRiderController.php` (vendor-side, in V1)

### Form Requests (12)
- Create: `app/Http/Requests/Api/Rider/V1/RegisterRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/LoginRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/VerifyOtpRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/SubmitDocumentsRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/UpdateLocationRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/AcceptDeliveryRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/ConfirmDeliveryRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/CancelDeliveryRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/WithdrawalRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/UpdateProfileRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/UpdateVehicleRequest.php`
- Create: `app/Http/Requests/Api/Rider/V1/DispatchDeliveryRequest.php`

### Resources (7)
- Create: `app/Http/Resources/Api/Rider/V1/RiderResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/DeliveryRequestResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/RiderEarningResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/WithdrawalRequestResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/DeliveryHistoryResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/DashboardResource.php`
- Create: `app/Http/Resources/Api/Rider/V1/VendorRiderResource.php`

### Jobs (1)
- Create: `app/Jobs/BroadcastDeliveryRequest.php`

### Events (2)
- Create: `app/Events/DeliveryRequestCreated.php`
- Create: `app/Events/DeliveryStatusUpdated.php`

### Notifications (1)
- Create: `app/Notifications/NewDeliveryRequestNotification.php`

### Tests (6)
- Create: `tests/Feature/Rider/RiderAuthTest.php`
- Create: `tests/Feature/Rider/RiderOnboardingTest.php`
- Create: `tests/Feature/Rider/RiderDashboardTest.php`
- Create: `tests/Feature/Rider/RiderDeliveryTest.php`
- Create: `tests/Feature/Rider/RiderEarningTest.php`
- Create: `tests/Feature/Rider/VendorRiderTest.php`

---

## Chunk 1: Database Foundation & Configuration

### Task 1: Update auth config for rider guard

**Files:**
- Modify: `config/auth.php`

- [ ] **Step 1: Add rider guard and provider to auth config**

Add to the `guards` array:
```php
'rider' => [
    'driver' => 'sanctum',
    'provider' => 'riders',
],
```

Add to the `providers` array:
```php
'riders' => [
    'driver' => 'eloquent',
    'model' => App\Models\Rider::class,
],
```

- [ ] **Step 2: Commit**
```bash
git add config/auth.php
git commit -m "feat(rider): add rider guard and provider to auth config"
```

### Task 2: Create migrations

**Files:**
- Create: 6 migration files in `database/migrations/`

- [ ] **Step 1: Create migration to update riders table**

Run: `php artisan make:migration update_riders_table_for_authentication --table=riders --no-interaction`

Content of the migration `up()`:
```php
public function up(): void
{
    Schema::table('riders', function (Blueprint $table) {
        $table->string('password')->after('email');
        $table->timestamp('email_verified_at')->nullable()->after('password');
        $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        $table->string('ghana_card_front')->nullable()->after('phone_verified_at');
        $table->string('ghana_card_back')->nullable()->after('ghana_card_front');
        $table->string('drivers_license')->nullable()->after('ghana_card_back');
        $table->string('vehicle_photo')->nullable()->after('drivers_license');
        $table->enum('vehicle_category', ['motorbike', 'car'])->default('motorbike')->after('vehicle_photo');
        $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'suspended'])->default('pending')->after('vehicle_category');
        $table->boolean('is_online')->default(false)->after('status');
        $table->decimal('current_latitude', 10, 7)->nullable()->after('is_online');
        $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
        $table->timestamp('location_updated_at')->nullable()->after('current_longitude');
        $table->string('device_token')->nullable()->after('location_updated_at');
        $table->decimal('average_rating', 3, 2)->default(0)->after('device_token');
        $table->unsignedInteger('total_deliveries')->default(0)->after('average_rating');
        $table->rememberToken()->after('total_deliveries');
    });
}

public function down(): void
{
    Schema::table('riders', function (Blueprint $table) {
        $table->dropColumn([
            'password', 'email_verified_at', 'phone_verified_at',
            'ghana_card_front', 'ghana_card_back', 'drivers_license',
            'vehicle_photo', 'vehicle_category', 'status', 'is_online',
            'current_latitude', 'current_longitude', 'location_updated_at',
            'device_token', 'average_rating', 'total_deliveries', 'remember_token',
        ]);
    });
}
```

- [ ] **Step 2: Create delivery_requests migration**

Run: `php artisan make:migration create_delivery_requests_table --no-interaction`

```php
public function up(): void
{
    Schema::create('delivery_requests', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('order_id')->constrained()->onDelete('cascade');
        $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
        $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('assigned_rider_id')->nullable()->constrained('riders')->nullOnDelete();
        $table->enum('status', [
            'broadcasting', 'assigned', 'accepted', 'picked_up',
            'in_transit', 'delivered', 'cancelled', 'expired',
        ])->default('broadcasting');
        $table->string('pickup_address');
        $table->decimal('pickup_latitude', 10, 7);
        $table->decimal('pickup_longitude', 10, 7);
        $table->string('dropoff_address');
        $table->decimal('dropoff_latitude', 10, 7);
        $table->decimal('dropoff_longitude', 10, 7);
        $table->decimal('delivery_fee', 10, 2);
        $table->decimal('distance_km', 8, 2)->nullable();
        $table->decimal('broadcast_radius_km', 5, 2)->default(5.00);
        $table->unsignedTinyInteger('broadcast_attempts')->default(0);
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('picked_up_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->string('cancellation_reason')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->index('status');
        $table->index(['rider_id', 'status']);
        $table->index(['vendor_id', 'status']);
        $table->index('order_id');
    });
}
```

- [ ] **Step 3: Create rider_earnings migration**

Run: `php artisan make:migration create_rider_earnings_table --no-interaction`

```php
public function up(): void
{
    Schema::create('rider_earnings', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
        $table->foreignId('order_id')->constrained()->onDelete('cascade');
        $table->uuid('delivery_request_id');
        $table->foreign('delivery_request_id')->references('id')->on('delivery_requests')->onDelete('cascade');
        $table->decimal('amount', 10, 2);
        $table->enum('type', ['delivery_fee', 'bonus', 'adjustment'])->default('delivery_fee');
        $table->enum('status', ['pending', 'available', 'withdrawn'])->default('pending');
        $table->timestamp('available_at')->nullable();
        $table->timestamps();

        $table->index(['rider_id', 'status']);
    });
}
```

- [ ] **Step 4: Create rider_withdrawal_requests migration**

Run: `php artisan make:migration create_rider_withdrawal_requests_table --no-interaction`

```php
public function up(): void
{
    Schema::create('rider_withdrawal_requests', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
        $table->decimal('amount', 10, 2);
        $table->enum('status', ['pending', 'processing', 'completed', 'rejected', 'failed'])->default('pending');
        $table->enum('mobile_money_provider', ['mtn', 'vodafone', 'airteltigo']);
        $table->string('mobile_money_number');
        $table->timestamp('processed_at')->nullable();
        $table->string('rejection_reason')->nullable();
        $table->timestamps();

        $table->index(['rider_id', 'status']);
    });
}
```

- [ ] **Step 5: Create vendor_riders migration**

Run: `php artisan make:migration create_vendor_riders_table --no-interaction`

```php
public function up(): void
{
    Schema::create('vendor_riders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
        $table->string('nickname')->nullable();
        $table->boolean('is_default')->default(false);
        $table->timestamps();

        $table->unique(['vendor_id', 'rider_id']);
    });
}
```

- [ ] **Step 6: Create rider_location_logs migration**

Run: `php artisan make:migration create_rider_location_logs_table --no-interaction`

```php
public function up(): void
{
    Schema::create('rider_location_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
        $table->uuid('delivery_request_id');
        $table->foreign('delivery_request_id')->references('id')->on('delivery_requests')->onDelete('cascade');
        $table->decimal('latitude', 10, 7);
        $table->decimal('longitude', 10, 7);
        $table->timestamp('recorded_at');

        $table->index(['delivery_request_id', 'recorded_at']);
    });
}
```

- [ ] **Step 7: Run migrations**

Run: `php artisan migrate --no-interaction`
Expected: All 6 migrations run successfully.

- [ ] **Step 8: Commit**
```bash
git add database/migrations/
git commit -m "feat(rider): add rider app database migrations"
```

### Task 3: Update Rider model for authentication

**Files:**
- Modify: `app/Models/Rider.php`

- [ ] **Step 1: Rewrite Rider model**

The Rider model must extend `Authenticatable` instead of `Model`, and add `HasApiTokens`, `HasFactory`, `Notifiable` traits:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'password',
        'email_verified_at', 'phone_verified_at',
        'vehicle_type', 'license_plate', 'id_card_number',
        'ghana_card_front', 'ghana_card_back', 'drivers_license',
        'vehicle_photo', 'vehicle_category', 'status',
        'is_active', 'is_online',
        'current_latitude', 'current_longitude', 'location_updated_at',
        'device_token', 'average_rating', 'total_deliveries',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_active_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'password' => 'hashed',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'average_rating' => 'decimal:2',
            'total_deliveries' => 'integer',
        ];
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // --- Status checks ---

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // --- Relationships ---

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryRequests(): HasMany
    {
        return $this->hasMany(DeliveryRequest::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(RiderWithdrawalRequest::class);
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vendor_riders', 'rider_id', 'vendor_id')
            ->withPivot('nickname', 'is_default')
            ->withTimestamps();
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(RiderLocationLog::class);
    }

    // --- Balance helpers ---

    public function getAvailableBalanceAttribute(): float
    {
        return (float) $this->earnings()
            ->where('status', 'available')
            ->sum('amount');
    }

    public function getPendingBalanceAttribute(): float
    {
        return (float) $this->earnings()
            ->where('status', 'pending')
            ->sum('amount');
    }

    // --- Scopes ---

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeNearby($query, float $latitude, float $longitude, float $radiusKm)
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(current_latitude)) * cos(radians(current_longitude) - radians(?)) + sin(radians(?)) * sin(radians(current_latitude))))";

        return $query
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusKm]);
    }
}
```

- [ ] **Step 2: Commit**
```bash
git add app/Models/Rider.php
git commit -m "feat(rider): extend Rider model with auth, relationships, and scopes"
```

### Task 4: Create new models

**Files:**
- Create: `app/Models/DeliveryRequest.php`
- Create: `app/Models/RiderEarning.php`
- Create: `app/Models/RiderWithdrawalRequest.php`
- Create: `app/Models/VendorRider.php`
- Create: `app/Models/RiderLocationLog.php`

- [ ] **Step 1: Create DeliveryRequest model**

Run: `php artisan make:model DeliveryRequest --no-interaction`

Replace content:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_id', 'rider_id', 'vendor_id', 'assigned_rider_id',
        'status', 'pickup_address', 'pickup_latitude', 'pickup_longitude',
        'dropoff_address', 'dropoff_latitude', 'dropoff_longitude',
        'delivery_fee', 'distance_km', 'broadcast_radius_km',
        'broadcast_attempts', 'accepted_at', 'picked_up_at',
        'delivered_at', 'expires_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'delivery_fee' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'broadcast_radius_km' => 'decimal:2',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function assignedRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'assigned_rider_id');
    }

    public function earning(): HasOne
    {
        return $this->hasOne(RiderEarning::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(RiderLocationLog::class);
    }

    // --- Status checks ---

    public function isBroadcasting(): bool
    {
        return $this->status === 'broadcasting';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['accepted', 'picked_up', 'in_transit']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['accepted', 'picked_up', 'in_transit']);
    }

    public function scopeBroadcasting($query)
    {
        return $query->where('status', 'broadcasting');
    }
}
```

- [ ] **Step 2: Create RiderEarning model**

Run: `php artisan make:model RiderEarning --no-interaction`

Replace content:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderEarning extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rider_id', 'order_id', 'delivery_request_id',
        'amount', 'type', 'status', 'available_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
```

- [ ] **Step 3: Create RiderWithdrawalRequest model**

Run: `php artisan make:model RiderWithdrawalRequest --no-interaction`

Replace content:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderWithdrawalRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rider_id', 'amount', 'status',
        'mobile_money_provider', 'mobile_money_number',
        'processed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
```

- [ ] **Step 4: Create VendorRider model**

Run: `php artisan make:model VendorRider --no-interaction`

Replace content:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRider extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'rider_id', 'nickname', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
```

- [ ] **Step 5: Create RiderLocationLog model**

Run: `php artisan make:model RiderLocationLog --no-interaction`

Replace content:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderLocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rider_id', 'delivery_request_id', 'latitude', 'longitude', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }
}
```

- [ ] **Step 6: Commit**
```bash
git add app/Models/
git commit -m "feat(rider): add DeliveryRequest, RiderEarning, WithdrawalRequest, VendorRider, LocationLog models"
```

### Task 5: Create factories

**Files:**
- Create/Modify: factory files for all rider models

- [ ] **Step 1: Update RiderFactory**

Modify `database/factories/RiderFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class RiderFactory extends Factory
{
    protected $model = Rider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '024' . fake()->unique()->numberBetween(1000000, 9999999),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'vehicle_type' => fake()->randomElement(['motorcycle', 'car']),
            'vehicle_category' => fake()->randomElement(['motorbike', 'car']),
            'license_plate' => strtoupper(fake()->bothify('??-####-##')),
            'id_card_number' => 'GHA-' . fake()->numerify('#########'),
            'status' => 'approved',
            'is_active' => true,
            'is_online' => false,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'average_rating' => fake()->randomFloat(2, 3.0, 5.0),
            'total_deliveries' => fake()->numberBetween(0, 200),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function underReview(): static
    {
        return $this->state(fn () => ['status' => 'under_review']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'is_online' => true,
            'current_latitude' => fake()->latitude(5.5, 6.0),
            'current_longitude' => fake()->longitude(-0.3, 0.1),
            'location_updated_at' => now(),
        ]);
    }

    public function withDocuments(): static
    {
        return $this->state(fn () => [
            'ghana_card_front' => 'documents/ghana_card_front.jpg',
            'ghana_card_back' => 'documents/ghana_card_back.jpg',
            'drivers_license' => 'documents/drivers_license.jpg',
            'vehicle_photo' => 'documents/vehicle_photo.jpg',
        ]);
    }
}
```

- [ ] **Step 2: Create DeliveryRequestFactory**

Run: `php artisan make:factory DeliveryRequestFactory --no-interaction`

```php
<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryRequestFactory extends Factory
{
    protected $model = DeliveryRequest::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => User::factory()->vendor(),
            'rider_id' => null,
            'assigned_rider_id' => null,
            'status' => 'broadcasting',
            'pickup_address' => fake()->address(),
            'pickup_latitude' => fake()->latitude(5.5, 6.0),
            'pickup_longitude' => fake()->longitude(-0.3, 0.1),
            'dropoff_address' => fake()->address(),
            'dropoff_latitude' => fake()->latitude(5.5, 6.0),
            'dropoff_longitude' => fake()->longitude(-0.3, 0.1),
            'delivery_fee' => fake()->randomFloat(2, 10, 100),
            'distance_km' => fake()->randomFloat(2, 1, 30),
            'broadcast_radius_km' => 5.00,
            'broadcast_attempts' => 0,
            'expires_at' => now()->addSeconds(30),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'rider_id' => Rider::factory(),
            'accepted_at' => now(),
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state(fn () => [
            'status' => 'picked_up',
            'rider_id' => Rider::factory(),
            'accepted_at' => now()->subMinutes(10),
            'picked_up_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'rider_id' => Rider::factory(),
            'accepted_at' => now()->subMinutes(30),
            'picked_up_at' => now()->subMinutes(20),
            'delivered_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Create remaining factories**

Create `RiderEarningFactory`, `RiderWithdrawalRequestFactory`, `VendorRiderFactory` following the same pattern. Use `php artisan make:factory <Name> --no-interaction` for each.

- [ ] **Step 4: Commit**
```bash
git add database/factories/
git commit -m "feat(rider): add factories for rider models"
```

### Task 6: Register rider routes and middleware

**Files:**
- Create: `routes/api_rider.php`
- Modify: `bootstrap/app.php`
- Create: `app/Http/Middleware/EnsureRiderApproved.php`

- [ ] **Step 1: Create EnsureRiderApproved middleware**

Run: `php artisan make:middleware EnsureRiderApproved --no-interaction`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $rider = $request->user('rider');

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($rider->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        if (! $rider->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval.',
                'data' => ['status' => $rider->status],
            ], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Create rider route file**

Create `routes/api_rider.php`:
```php
<?php

use App\Http\Controllers\Api\Rider\V1\AuthController;
use App\Http\Controllers\Api\Rider\V1\DashboardController;
use App\Http\Controllers\Api\Rider\V1\DeliveryController;
use App\Http\Controllers\Api\Rider\V1\EarningController;
use App\Http\Controllers\Api\Rider\V1\OnboardingController;
use App\Http\Controllers\Api\Rider\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('otp/send', [AuthController::class, 'sendOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Authenticated routes (any approved status)
    Route::middleware('auth:rider')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Onboarding (pre-approval)
        Route::prefix('onboarding')->group(function () {
            Route::post('documents', [OnboardingController::class, 'submitDocuments']);
            Route::get('status', [OnboardingController::class, 'status']);
            Route::put('documents', [OnboardingController::class, 'resubmitDocuments']);
        });

        // Approved riders only
        Route::middleware('rider.approved')->group(function () {
            // Dashboard
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::post('dashboard/toggle-online', [DashboardController::class, 'toggleOnline']);
            Route::post('dashboard/location', [DashboardController::class, 'updateLocation']);
            Route::put('dashboard/device-token', [DashboardController::class, 'updateDeviceToken']);

            // Deliveries
            Route::prefix('deliveries')->group(function () {
                Route::get('incoming', [DeliveryController::class, 'incoming']);
                Route::get('active', [DeliveryController::class, 'active']);
                Route::get('history', [DeliveryController::class, 'history']);
                Route::get('{deliveryRequest}', [DeliveryController::class, 'show']);
                Route::post('{deliveryRequest}/accept', [DeliveryController::class, 'accept']);
                Route::post('{deliveryRequest}/decline', [DeliveryController::class, 'decline']);
                Route::post('{deliveryRequest}/pickup', [DeliveryController::class, 'pickup']);
                Route::post('{deliveryRequest}/deliver', [DeliveryController::class, 'deliver']);
                Route::post('{deliveryRequest}/cancel', [DeliveryController::class, 'cancel']);
            });

            // Earnings
            Route::prefix('earnings')->group(function () {
                Route::get('/', [EarningController::class, 'index']);
                Route::get('transactions', [EarningController::class, 'transactions']);
                Route::post('withdraw', [EarningController::class, 'withdraw']);
                Route::get('withdrawals', [EarningController::class, 'withdrawals']);
            });

            // Profile
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('profile/vehicle', [ProfileController::class, 'updateVehicle']);
            Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        });
    });
});
```

- [ ] **Step 3: Register route file and middleware in bootstrap/app.php**

Add to the `withRouting` closure:
```php
->withRouting(
    // ... existing routes ...
    then: function () {
        Route::middleware('api')
            ->prefix('api/rider')
            ->group(base_path('routes/api_rider.php'));
    },
)
```

Add middleware alias in `withMiddleware`:
```php
->withMiddleware(function (Middleware $middleware) {
    // ... existing middleware ...
    $middleware->alias([
        // ... existing aliases ...
        'rider.approved' => \App\Http\Middleware\EnsureRiderApproved::class,
    ]);
})
```

- [ ] **Step 4: Commit**
```bash
git add routes/api_rider.php bootstrap/app.php app/Http/Middleware/EnsureRiderApproved.php
git commit -m "feat(rider): add rider route file, middleware, and bootstrap registration"
```

---

## Chunk 2: Authentication & Onboarding

### Task 7: Create rider form requests

**Files:**
- Create: Form request classes in `app/Http/Requests/Api/Rider/V1/`

- [ ] **Step 1: Create RegisterRequest**

Run: `php artisan make:request Api/Rider/V1/RegisterRequest --no-interaction`

```php
<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:riders,email',
            'phone' => 'required|string|max:20|unique:riders,phone',
            'password' => 'required|string|min:8|confirmed',
            'vehicle_category' => 'required|in:motorbike,car',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A rider account with this email already exists.',
            'phone.unique' => 'A rider account with this phone number already exists.',
        ];
    }
}
```

- [ ] **Step 2: Create LoginRequest**

Run: `php artisan make:request Api/Rider/V1/LoginRequest --no-interaction`

```php
<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string',
        ];
    }
}
```

- [ ] **Step 3: Create remaining form requests**

Create `VerifyOtpRequest`, `SubmitDocumentsRequest`, `UpdateLocationRequest`, `ConfirmDeliveryRequest`, `CancelDeliveryRequest`, `WithdrawalRequest`, `UpdateProfileRequest`, `UpdateVehicleRequest`, `DispatchDeliveryRequest` using `php artisan make:request` with appropriate validation rules matching the design doc fields.

- [ ] **Step 4: Commit**
```bash
git add app/Http/Requests/Api/Rider/
git commit -m "feat(rider): add form request validation classes"
```

### Task 8: Create rider API resources

**Files:**
- Create: Resource classes in `app/Http/Resources/Api/Rider/V1/`

- [ ] **Step 1: Create RiderResource**

Run: `php artisan make:resource Api/Rider/V1/RiderResource --no-interaction`

```php
<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_category' => $this->vehicle_category,
            'license_plate' => $this->license_plate,
            'status' => $this->status,
            'is_online' => (bool) $this->is_online,
            'average_rating' => (float) $this->average_rating,
            'total_deliveries' => (int) $this->total_deliveries,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Create DeliveryRequestResource**

Run: `php artisan make:resource Api/Rider/V1/DeliveryRequestResource --no-interaction`

```php
<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => (int) $this->order_id,
            'status' => $this->status,
            'pickup_address' => $this->pickup_address,
            'pickup_latitude' => (float) $this->pickup_latitude,
            'pickup_longitude' => (float) $this->pickup_longitude,
            'dropoff_address' => $this->dropoff_address,
            'dropoff_latitude' => (float) $this->dropoff_latitude,
            'dropoff_longitude' => (float) $this->dropoff_longitude,
            'delivery_fee' => (float) $this->delivery_fee,
            'distance_km' => $this->distance_km ? (float) $this->distance_km : null,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'picked_up_at' => $this->picked_up_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'order' => $this->whenLoaded('order', fn () => [
                'order_number' => $this->order->order_number,
                'receiver_name' => $this->order->receiver_name,
                'receiver_phone' => $this->order->receiver_phone,
                'delivery_pin' => $this->when($this->isActive(), $this->order->delivery_pin),
                'special_instructions' => $this->order->special_instructions,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => (int) $this->vendor->id,
                'name' => $this->vendor->name,
                'phone' => $this->vendor->phone,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 3: Create remaining resources**

Create `RiderEarningResource`, `WithdrawalRequestResource`, `DashboardResource`, `VendorRiderResource` similarly.

- [ ] **Step 4: Commit**
```bash
git add app/Http/Resources/Api/Rider/
git commit -m "feat(rider): add API resource classes for rider responses"
```

### Task 9: Create rider AuthController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/AuthController.php`
- Test: `tests/Feature/Rider/RiderAuthTest.php`

- [ ] **Step 1: Write the test**

Run: `php artisan make:test Rider/RiderAuthTest --phpunit --no-interaction`

```php
<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_register(): void
    {
        $response = $this->postJson('/api/rider/v1/auth/register', [
            'name' => 'Test Rider',
            'email' => 'rider@test.com',
            'phone' => '0241234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'vehicle_category' => 'motorbike',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['rider', 'token', 'token_type'],
            ]);

        $this->assertDatabaseHas('riders', [
            'email' => 'rider@test.com',
            'status' => 'pending',
        ]);
    }

    public function test_rider_cannot_register_with_existing_email(): void
    {
        Rider::factory()->create(['email' => 'rider@test.com']);

        $response = $this->postJson('/api/rider/v1/auth/register', [
            'name' => 'Test Rider',
            'email' => 'rider@test.com',
            'phone' => '0241234568',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'vehicle_category' => 'motorbike',
        ]);

        $response->assertStatus(422);
    }

    public function test_rider_can_login(): void
    {
        $rider = Rider::factory()->create([
            'email' => 'rider@test.com',
        ]);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['rider', 'token', 'token_type'],
            ]);
    }

    public function test_rider_cannot_login_with_wrong_password(): void
    {
        Rider::factory()->create(['email' => 'rider@test.com']);

        $response = $this->postJson('/api/rider/v1/auth/login', [
            'email' => 'rider@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_rider_can_logout(): void
    {
        $rider = Rider::factory()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/auth/logout');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RiderAuthTest`
Expected: FAIL — controller class does not exist.

- [ ] **Step 3: Create AuthController**

Create directory and file `app/Http/Controllers/Api/Rider/V1/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\LoginRequest;
use App\Http\Requests\Api\Rider\V1\RegisterRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use App\Models\Rider;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(protected KairosAfrikaSmsService $smsService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $rider = Rider::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'vehicle_category' => $request->vehicle_category,
            'status' => 'pending',
        ]);

        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please upload your documents for verification.',
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $field = $request->has('email') ? 'email' : 'phone';
        $rider = Rider::where($field, $request->input($field))->first();

        if (! $rider || ! Hash::check($request->password, $rider->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('rider')->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);

        $rider = Rider::where('phone', $request->phone)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'No rider found with this phone number.',
            ], 404);
        }

        $this->smsService->sendOtp($rider->phone);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        $verified = $this->smsService->verifyOtp($request->phone, $request->otp);

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $rider = Rider::where('phone', $request->phone)->first();

        if ($rider) {
            $rider->update(['phone_verified_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $rider = Rider::where('email', $request->email)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'No rider found with this email.',
            ], 404);
        }

        // TODO: Dispatch password reset job for rider

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // TODO: Validate reset token and update password

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=RiderAuthTest`
Expected: All 5 tests pass.

- [ ] **Step 5: Commit**
```bash
git add app/Http/Controllers/Api/Rider/ tests/Feature/Rider/ app/Http/Requests/Api/Rider/
git commit -m "feat(rider): add rider auth controller with register, login, logout, OTP"
```

### Task 10: Create OnboardingController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/OnboardingController.php`
- Test: `tests/Feature/Rider/RiderOnboardingTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_submit_documents(): void
    {
        Storage::fake('s3');
        $rider = Rider::factory()->pending()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/onboarding/documents', [
                'ghana_card_front' => UploadedFile::fake()->image('front.jpg'),
                'ghana_card_back' => UploadedFile::fake()->image('back.jpg'),
                'drivers_license' => UploadedFile::fake()->image('license.jpg'),
                'vehicle_photo' => UploadedFile::fake()->image('vehicle.jpg'),
                'vehicle_type' => 'Honda CG 125',
                'license_plate' => 'GR-1234-21',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'status' => 'under_review',
        ]);
    }

    public function test_rider_can_check_onboarding_status(): void
    {
        $rider = Rider::factory()->underReview()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/onboarding/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'under_review'],
            ]);
    }

    public function test_approved_rider_cannot_resubmit_documents(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/onboarding/documents', []);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Create OnboardingController**

```php
<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\SubmitDocumentsRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function submitDocuments(SubmitDocumentsRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        if ($rider->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is already approved.',
            ], 403);
        }

        $data = [
            'vehicle_type' => $request->vehicle_type,
            'license_plate' => $request->license_plate,
            'status' => 'under_review',
        ];

        foreach (['ghana_card_front', 'ghana_card_back', 'drivers_license', 'vehicle_photo'] as $doc) {
            if ($request->hasFile($doc)) {
                $data[$doc] = $request->file($doc)->store("riders/{$rider->id}/{$doc}", 's3');
            }
        }

        $rider->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Documents submitted for review.',
            'data' => new RiderResource($rider->fresh()),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $rider->status,
                'has_documents' => (bool) $rider->ghana_card_front,
                'rider' => new RiderResource($rider),
            ],
        ]);
    }

    public function resubmitDocuments(SubmitDocumentsRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        if (! $rider->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only resubmit documents after rejection.',
            ], 403);
        }

        return $this->submitDocuments($request);
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=RiderOnboardingTest`

- [ ] **Step 4: Commit**
```bash
git add app/Http/Controllers/Api/Rider/V1/OnboardingController.php tests/Feature/Rider/RiderOnboardingTest.php
git commit -m "feat(rider): add onboarding controller with document upload and status check"
```

---

## Chunk 3: Dashboard, Deliveries & Dispatch

### Task 11: Create services

**Files:**
- Create: `app/Services/RiderBalanceService.php`
- Create: `app/Services/DeliveryDispatchService.php`

- [ ] **Step 1: Create RiderBalanceService**

```php
<?php

namespace App\Services;

use App\Models\DeliveryRequest;
use App\Models\Rider;
use App\Models\RiderEarning;

class RiderBalanceService
{
    public function creditEarning(Rider $rider, DeliveryRequest $deliveryRequest): RiderEarning
    {
        return RiderEarning::create([
            'rider_id' => $rider->id,
            'order_id' => $deliveryRequest->order_id,
            'delivery_request_id' => $deliveryRequest->id,
            'amount' => $deliveryRequest->delivery_fee,
            'type' => 'delivery_fee',
            'status' => 'pending',
            'available_at' => now()->addHours(24),
        ]);
    }

    public function releasePendingEarnings(): int
    {
        return RiderEarning::where('status', 'pending')
            ->where('available_at', '<=', now())
            ->update(['status' => 'available']);
    }

    public function getBalanceSummary(Rider $rider): array
    {
        return [
            'available' => (float) $rider->earnings()->available()->sum('amount'),
            'pending' => (float) $rider->earnings()->pending()->sum('amount'),
            'total_earned' => (float) $rider->earnings()->sum('amount'),
            'total_withdrawn' => (float) $rider->withdrawalRequests()
                ->where('status', 'completed')->sum('amount'),
        ];
    }

    public function processWithdrawal(Rider $rider, float $amount, string $provider, string $number): mixed
    {
        $available = (float) $rider->earnings()->available()->sum('amount');

        if ($amount > $available) {
            return null;
        }

        return $rider->withdrawalRequests()->create([
            'amount' => $amount,
            'mobile_money_provider' => $provider,
            'mobile_money_number' => $number,
            'status' => 'pending',
        ]);
    }
}
```

- [ ] **Step 2: Create DeliveryDispatchService**

```php
<?php

namespace App\Services;

use App\Jobs\BroadcastDeliveryRequest;
use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;

class DeliveryDispatchService
{
    private const BROADCAST_RADII = [5, 10, 20];
    private const BROADCAST_TIMEOUT_SECONDS = 30;
    private const MAX_BROADCAST_ATTEMPTS = 3;

    public function createDeliveryRequest(
        Order $order,
        int $vendorId,
        string $pickupAddress,
        float $pickupLat,
        float $pickupLng,
        string $dropoffAddress,
        float $dropoffLat,
        float $dropoffLng,
        float $deliveryFee,
        ?int $assignedRiderId = null,
    ): DeliveryRequest {
        $deliveryRequest = DeliveryRequest::create([
            'order_id' => $order->id,
            'vendor_id' => $vendorId,
            'assigned_rider_id' => $assignedRiderId,
            'status' => $assignedRiderId ? 'assigned' : 'broadcasting',
            'pickup_address' => $pickupAddress,
            'pickup_latitude' => $pickupLat,
            'pickup_longitude' => $pickupLng,
            'dropoff_address' => $dropoffAddress,
            'dropoff_latitude' => $dropoffLat,
            'dropoff_longitude' => $dropoffLng,
            'delivery_fee' => $deliveryFee,
            'distance_km' => $this->calculateDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng),
            'broadcast_radius_km' => self::BROADCAST_RADII[0],
            'expires_at' => now()->addSeconds(self::BROADCAST_TIMEOUT_SECONDS),
        ]);

        if ($assignedRiderId) {
            $this->notifyAssignedRider($deliveryRequest);
        } else {
            BroadcastDeliveryRequest::dispatch($deliveryRequest);
        }

        return $deliveryRequest;
    }

    public function broadcastToNearbyRiders(DeliveryRequest $deliveryRequest): void
    {
        if ($deliveryRequest->broadcast_attempts >= self::MAX_BROADCAST_ATTEMPTS) {
            $deliveryRequest->update(['status' => 'expired']);
            return;
        }

        $radiusIndex = min($deliveryRequest->broadcast_attempts, count(self::BROADCAST_RADII) - 1);
        $radius = self::BROADCAST_RADII[$radiusIndex];

        $riders = Rider::query()
            ->approved()
            ->online()
            ->nearby($deliveryRequest->pickup_latitude, $deliveryRequest->pickup_longitude, $radius)
            ->whereDoesntHave('deliveryRequests', fn ($q) => $q->active())
            ->get();

        foreach ($riders as $rider) {
            $rider->notify(new \App\Notifications\NewDeliveryRequestNotification($deliveryRequest));
        }

        $deliveryRequest->update([
            'broadcast_attempts' => $deliveryRequest->broadcast_attempts + 1,
            'broadcast_radius_km' => $radius,
            'expires_at' => now()->addSeconds(self::BROADCAST_TIMEOUT_SECONDS),
        ]);
    }

    public function acceptDelivery(DeliveryRequest $deliveryRequest, Rider $rider): bool
    {
        if (! in_array($deliveryRequest->status, ['broadcasting', 'assigned'])) {
            return false;
        }

        if ($deliveryRequest->status === 'assigned' && $deliveryRequest->assigned_rider_id !== $rider->id) {
            return false;
        }

        $deliveryRequest->update([
            'rider_id' => $rider->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $deliveryRequest->order->update([
            'rider_id' => $rider->id,
            'status' => 'confirmed',
        ]);

        return true;
    }

    public function declineDelivery(DeliveryRequest $deliveryRequest, Rider $rider): void
    {
        if ($deliveryRequest->status === 'assigned' && $deliveryRequest->assigned_rider_id === $rider->id) {
            $deliveryRequest->update(['assigned_rider_id' => null, 'status' => 'broadcasting']);
            BroadcastDeliveryRequest::dispatch($deliveryRequest);
        }
    }

    private function notifyAssignedRider(DeliveryRequest $deliveryRequest): void
    {
        $rider = Rider::find($deliveryRequest->assigned_rider_id);
        if ($rider) {
            $rider->notify(new \App\Notifications\NewDeliveryRequestNotification($deliveryRequest));
        }
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
```

- [ ] **Step 3: Create BroadcastDeliveryRequest job**

Run: `php artisan make:job BroadcastDeliveryRequest --no-interaction`

```php
<?php

namespace App\Jobs;

use App\Models\DeliveryRequest;
use App\Services\DeliveryDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastDeliveryRequest implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(public DeliveryRequest $deliveryRequest) {}

    public function handle(DeliveryDispatchService $dispatchService): void
    {
        if ($this->deliveryRequest->status === 'accepted') {
            return;
        }

        $dispatchService->broadcastToNearbyRiders($this->deliveryRequest);

        if ($this->deliveryRequest->fresh()->status === 'broadcasting') {
            self::dispatch($this->deliveryRequest->fresh())
                ->delay(now()->addSeconds(30));
        }
    }
}
```

- [ ] **Step 4: Create NewDeliveryRequestNotification**

Run: `php artisan make:notification NewDeliveryRequestNotification --no-interaction`

```php
<?php

namespace App\Notifications;

use App\Models\DeliveryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewDeliveryRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DeliveryRequest $deliveryRequest)
    {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'delivery_request_id' => $this->deliveryRequest->id,
            'pickup_address' => $this->deliveryRequest->pickup_address,
            'dropoff_address' => $this->deliveryRequest->dropoff_address,
            'delivery_fee' => $this->deliveryRequest->delivery_fee,
            'distance_km' => $this->deliveryRequest->distance_km,
            'expires_at' => $this->deliveryRequest->expires_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 5: Commit**
```bash
git add app/Services/RiderBalanceService.php app/Services/DeliveryDispatchService.php app/Jobs/BroadcastDeliveryRequest.php app/Notifications/NewDeliveryRequestNotification.php
git commit -m "feat(rider): add dispatch service, balance service, broadcast job, notification"
```

### Task 12: Create DashboardController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/DashboardController.php`
- Test: `tests/Feature/Rider/RiderDashboardTest.php`

- [ ] **Step 1: Write test, then controller**

Follow the same TDD pattern. Dashboard returns today's earnings, total deliveries, online toggle, and location update.

- [ ] **Step 2: Commit**

### Task 13: Create DeliveryController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/DeliveryController.php`
- Test: `tests/Feature/Rider/RiderDeliveryTest.php`

- [ ] **Step 1: Write test for incoming, accept, decline, pickup, deliver, cancel**
- [ ] **Step 2: Implement controller using DeliveryDispatchService and RiderBalanceService**
- [ ] **Step 3: Run tests**
- [ ] **Step 4: Commit**

---

## Chunk 4: Earnings, Profile & Vendor Integration

### Task 14: Create EarningController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/EarningController.php`
- Test: `tests/Feature/Rider/RiderEarningTest.php`

- [ ] **Step 1: Write test for balance summary, transactions, withdraw, withdrawal history**
- [ ] **Step 2: Implement controller using RiderBalanceService**
- [ ] **Step 3: Run tests**
- [ ] **Step 4: Commit**

### Task 15: Create ProfileController

**Files:**
- Create: `app/Http/Controllers/Api/Rider/V1/ProfileController.php`

- [ ] **Step 1: Implement show, update, updateVehicle, updatePassword**
- [ ] **Step 2: Commit**

### Task 16: Create vendor-side rider management

**Files:**
- Create: `app/Http/Controllers/Api/V1/VendorRiderController.php`
- Modify: `routes/api.php` — add vendor rider routes
- Test: `tests/Feature/Rider/VendorRiderTest.php`

- [ ] **Step 1: Write test for CRUD + dispatch**
- [ ] **Step 2: Implement controller**

Add to vendor route group in `routes/api.php`:
```php
// Inside the vendor middleware group
Route::prefix('vendor/riders')->group(function () {
    Route::get('/', [VendorRiderController::class, 'index']);
    Route::post('/', [VendorRiderController::class, 'store']);
    Route::delete('{vendorRider}', [VendorRiderController::class, 'destroy']);
});
Route::post('vendor/orders/{order}/dispatch', [VendorRiderController::class, 'dispatch']);
Route::get('vendor/orders/{order}/delivery-status', [VendorRiderController::class, 'deliveryStatus']);
```

- [ ] **Step 3: Run tests**
- [ ] **Step 4: Commit**

### Task 17: Create events for real-time tracking

**Files:**
- Create: `app/Events/DeliveryStatusUpdated.php`

- [ ] **Step 1: Create broadcasting event**

```php
<?php

namespace App\Events;

use App\Models\DeliveryRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeliveryRequest $deliveryRequest) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("delivery.{$this->deliveryRequest->id}"),
            new Channel("order.{$this->deliveryRequest->order_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_request_id' => $this->deliveryRequest->id,
            'status' => $this->deliveryRequest->status,
            'rider_latitude' => $this->deliveryRequest->rider?->current_latitude,
            'rider_longitude' => $this->deliveryRequest->rider?->current_longitude,
            'updated_at' => now()->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Commit**

### Task 18: Run Pint and full test suite

- [ ] **Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run full test suite**

Run: `php artisan test --compact`

- [ ] **Step 3: Fix any failures**
- [ ] **Step 4: Final commit**

### Task 19: Create Flutter team API documentation

- [ ] **Step 1: Write `docs/rider-app-flutter-api-guide.md`**

See separate document covering all endpoints, request/response schemas, auth flow, WebSocket channels, and error codes.

- [ ] **Step 2: Commit**
```bash
git add docs/
git commit -m "docs: add rider app API design, implementation plan, and Flutter team guide"
```
