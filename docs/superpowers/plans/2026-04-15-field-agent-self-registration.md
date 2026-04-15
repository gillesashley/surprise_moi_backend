# Field Agent Self-Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Public self-service field-agent registration with an admin approval flow, mirroring the existing VendorApplication KYC pattern. Approved applicants become full `field_agent` users fenced into their own area of the dashboard.

**Architecture:**
- Applications live in a new `field_agent_applications` table (pending → under_review → approved/rejected). On approval, a `User` row is created with role `field_agent` and the hashed password is copied over.
- Access control reuses the existing `EnsureDashboardAccess` middleware (it already redirects field agents to their dashboard) — we extend it only to whitelist profile/password/logout routes.
- Dual-channel notifications (mail + SMS) reuse the existing `SmsChannel` infrastructure.

**Tech Stack:** Laravel 12, Fortify, Sanctum, Inertia v2, React 19, Tailwind, PHPUnit 11. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-04-15-field-agent-self-registration-design.md`

**Branch:** `feat/field-agent-self-registration` (already created, spec committed).

**Important context the engineer should know before starting:**
- Admin routes are guarded by `['auth', 'dashboard']` — there is no separate `admin` middleware alias in use on these routes. `EnsureDashboardAccess::handle` delegates to `User::canAccessDashboard()` and performs role-based redirects. See `app/Http/Middleware/EnsureDashboardAccess.php`.
- SMS is already wired: implement `toSms(): SmsMessage` on a Notification and `routeNotificationForSms(): string` on the notifiable. Use `HasSmsChannel` trait and include `SmsChannel::class` in `via()`. Reference: `app/Notifications/Sms/OtpNotification.php`.
- Existing field-agent web routes live at `routes/web.php:225-236` under prefix `field-agent/` with name prefix `field-agent.`.
- Feature tests use `RefreshDatabase`, factory users with `->role` override, `actingAs()`, chainable assertions. Reference: `tests/Feature/AdminCategoryTest.php`.
- When modifying a column in a migration you MUST re-specify all attributes (Laravel 12 drops unspecified ones).
- After finalizing changes in any task: run `vendor/bin/pint --dirty --format agent` before committing.
- Use `php artisan test --compact --filter=<testname>` to run scoped tests.

---

## Task 1: Create `regions` migration, model, factory

**Files:**
- Create: `database/migrations/2026_04_15_120000_create_regions_table.php`
- Create: `app/Models/Region.php`
- Create: `database/factories/RegionFactory.php`
- Create: `tests/Unit/RegionCityTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_regions_table --no-interaction`

(Artisan will name the file with a timestamp prefix — rename only if needed to keep a sensible order.)

- [ ] **Step 2: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
```

- [ ] **Step 3: Generate model + factory**

Run: `php artisan make:model Region --factory --no-interaction`

- [ ] **Step 4: Write `app/Models/Region.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<\Database\Factories\RegionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
```

- [ ] **Step 5: Write `database/factories/RegionFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Region> */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
        ];
    }
}
```

- [ ] **Step 6: Write unit test for Region (stub — cities assertion added in Task 2)**

Create `tests/Unit/RegionCityTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionCityTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_can_be_created_from_factory(): void
    {
        $region = Region::factory()->create();

        $this->assertDatabaseHas('regions', ['id' => $region->id]);
        $this->assertNotEmpty($region->slug);
    }
}
```

- [ ] **Step 7: Run migration and test**

Run: `php artisan migrate --no-interaction && php artisan test --compact --filter=RegionCityTest`
Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*create_regions_table.php app/Models/Region.php database/factories/RegionFactory.php tests/Unit/RegionCityTest.php
git commit -m "feat(field-agents): add regions table and model"
```

---

## Task 2: Create `cities` migration, model, factory

**Files:**
- Create: `database/migrations/2026_04_15_120001_create_cities_table.php`
- Create: `app/Models/City.php`
- Create: `database/factories/CityFactory.php`
- Modify: `tests/Unit/RegionCityTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_cities_table --no-interaction`

- [ ] **Step 2: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['region_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
```

- [ ] **Step 3: Generate model + factory**

Run: `php artisan make:model City --factory --no-interaction`

- [ ] **Step 4: Write `app/Models/City.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    /** @use HasFactory<\Database\Factories\CityFactory> */
    use HasFactory;

    protected $fillable = ['region_id', 'name', 'slug'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
```

- [ ] **Step 5: Write `database/factories/CityFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<City> */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
```

- [ ] **Step 6: Extend `tests/Unit/RegionCityTest.php` with relationship assertions**

Append these methods to the existing class:

```php
public function test_region_has_many_cities(): void
{
    $region = Region::factory()->create();
    \App\Models\City::factory()->count(3)->create(['region_id' => $region->id]);

    $this->assertCount(3, $region->cities);
}

public function test_city_belongs_to_region(): void
{
    $city = \App\Models\City::factory()->create();

    $this->assertInstanceOf(Region::class, $city->region);
}
```

- [ ] **Step 7: Run migration + tests**

Run: `php artisan migrate --no-interaction && php artisan test --compact --filter=RegionCityTest`
Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*create_cities_table.php app/Models/City.php database/factories/CityFactory.php tests/Unit/RegionCityTest.php
git commit -m "feat(field-agents): add cities table with region relationship"
```

---

## Task 3: Seed Ghana's 16 regions and their cities

**Files:**
- Create: `database/seeders/RegionCitySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register the new seeder)

**Context:** Use the 16 regions of Ghana per GSS 2019. City lists are curated — pick 5-10 well-known cities per region. This is bulk data; the seeder is idempotent-ish via `firstOrCreate`.

- [ ] **Step 1: Create the seeder file**

Run: `php artisan make:seeder RegionCitySeeder --no-interaction`

- [ ] **Step 2: Write `database/seeders/RegionCitySeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionCitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Greater Accra' => ['Accra', 'Tema', 'Madina', 'Teshie', 'Nungua', 'Ashaiman', 'Dodowa', 'Adenta'],
            'Ashanti' => ['Kumasi', 'Obuasi', 'Ejisu', 'Mampong', 'Konongo', 'Offinso', 'Bekwai'],
            'Western' => ['Takoradi', 'Sekondi', 'Tarkwa', 'Axim', 'Prestea', 'Shama'],
            'Western North' => ['Sefwi Wiawso', 'Bibiani', 'Juaboso', 'Enchi'],
            'Central' => ['Cape Coast', 'Winneba', 'Kasoa', 'Swedru', 'Elmina', 'Mankessim'],
            'Eastern' => ['Koforidua', 'Nkawkaw', 'Akosombo', 'Akim Oda', 'Suhum', 'Begoro'],
            'Volta' => ['Ho', 'Keta', 'Hohoe', 'Aflao', 'Kpando', 'Sogakope'],
            'Oti' => ['Dambai', 'Jasikan', 'Nkwanta', 'Kadjebi', 'Krachi'],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu', 'Gushegu', 'Karaga'],
            'Savannah' => ['Damongo', 'Salaga', 'Bole', 'Sawla'],
            'North East' => ['Nalerigu', 'Walewale', 'Gambaga', 'Chereponi'],
            'Upper East' => ['Bolgatanga', 'Bawku', 'Navrongo', 'Zebilla', 'Paga'],
            'Upper West' => ['Wa', 'Tumu', 'Lawra', 'Jirapa', 'Nadowli'],
            'Bono' => ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi'],
            'Bono East' => ['Techiman', 'Kintampo', 'Atebubu', 'Nkoranza'],
            'Ahafo' => ['Goaso', 'Kukuom', 'Hwidiem', 'Mim'],
        ];

        foreach ($data as $regionName => $cities) {
            $region = Region::firstOrCreate(
                ['slug' => Str::slug($regionName)],
                ['name' => $regionName]
            );

            foreach ($cities as $cityName) {
                City::firstOrCreate(
                    ['region_id' => $region->id, 'slug' => Str::slug($cityName)],
                    ['name' => $cityName]
                );
            }

            $this->command->info("Seeded region: {$regionName} ({$region->cities()->count()} cities)");
        }
    }
}
```

- [ ] **Step 3: Register seeder in `DatabaseSeeder`**

Open `database/seeders/DatabaseSeeder.php`. In the `run()` method, find the `$this->call([...])` or equivalent and add `RegionCitySeeder::class` to the array. If the file uses individual `$this->call(RegionCitySeeder::class)` lines, add one. Exact placement should be early in the seeding order (before any seeder that might reference regions).

- [ ] **Step 4: Run seeder and verify**

Run: `php artisan db:seed --class=RegionCitySeeder --no-interaction`
Expected: 16 "Seeded region:" info lines, all successful.

Verify with Tinker:
```bash
php artisan tinker --execute="echo App\Models\Region::count() . ' regions, ' . App\Models\City::count() . ' cities' . PHP_EOL;"
```
Expected: `16 regions, 80+ cities`.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/RegionCitySeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(field-agents): seed Ghana's 16 regions and cities"
```

---

## Task 4: Create `FieldAgentApplicationStatus` enum and application migration

**Files:**
- Create: `app/Enums/FieldAgentApplicationStatus.php`
- Create: `database/migrations/2026_04_15_120002_create_field_agent_applications_table.php`

- [ ] **Step 1: Create enum**

Create `app/Enums/FieldAgentApplicationStatus.php`:

```php
<?php

namespace App\Enums;

enum FieldAgentApplicationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /** @return array<int, string> */
    public static function reviewableStatuses(): array
    {
        return [self::Pending->value, self::UnderReview->value];
    }

    /** @return array<int, string> */
    public static function nonRejectedStatuses(): array
    {
        return [self::Pending->value, self::UnderReview->value, self::Approved->value];
    }
}
```

- [ ] **Step 2: Generate migration**

Run: `php artisan make:migration create_field_agent_applications_table --no-interaction`

- [ ] **Step 3: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_agent_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('contact_number');

            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('location');

            $table->string('ghana_card_number');
            $table->string('ghana_card_image_path');
            $table->string('selfie_path');

            $table->string('password')->nullable();

            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('email');
            $table->index('ghana_card_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_agent_applications');
    }
};
```

**Note:** Uniqueness on `email` and `ghana_card_number` is enforced at the validation layer (scoped to non-rejected rows), not the DB — MySQL partial unique indexes are awkward, so we defend at the app layer and keep a plain index for lookup speed.

- [ ] **Step 4: Run migration**

Run: `php artisan migrate --no-interaction`
Expected: `Migrated: ...create_field_agent_applications_table`.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/FieldAgentApplicationStatus.php database/migrations/*create_field_agent_applications_table.php
git commit -m "feat(field-agents): add field_agent_applications table and status enum"
```

---

## Task 5: Create `FieldAgentApplication` model and factory with unit tests

**Files:**
- Create: `app/Models/FieldAgentApplication.php`
- Create: `database/factories/FieldAgentApplicationFactory.php`
- Create: `tests/Unit/FieldAgentApplicationTest.php`

- [ ] **Step 1: Generate model + factory**

Run: `php artisan make:model FieldAgentApplication --factory --no-interaction`

- [ ] **Step 2: Write `app/Models/FieldAgentApplication.php`**

```php
<?php

namespace App\Models;

use App\Enums\FieldAgentApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class FieldAgentApplication extends Model
{
    /** @use HasFactory<\Database\Factories\FieldAgentApplicationFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'region_id',
        'city_id',
        'location',
        'ghana_card_number',
        'ghana_card_image_path',
        'selfie_path',
        'password',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'approved_user_id',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'status' => FieldAgentApplicationStatus::class,
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status->value, FieldAgentApplicationStatus::reviewableStatuses(), true);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->contact_number;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
```

- [ ] **Step 3: Write `database/factories/FieldAgentApplicationFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\City;
use App\Models\FieldAgentApplication;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<FieldAgentApplication> */
class FieldAgentApplicationFactory extends Factory
{
    protected $model = FieldAgentApplication::class;

    public function definition(): array
    {
        $region = Region::inRandomOrder()->first() ?? Region::factory()->create();
        $city = $region->cities()->inRandomOrder()->first() ?? City::factory()->create(['region_id' => $region->id]);

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'contact_number' => '+233' . fake()->numerify('#########'),
            'region_id' => $region->id,
            'city_id' => $city->id,
            'location' => fake()->streetName(),
            'ghana_card_number' => 'GHA-' . fake()->unique()->numerify('#########') . '-' . fake()->numberBetween(0, 9),
            'ghana_card_image_path' => 'field-agents/ghana-cards/sample.jpg',
            'selfie_path' => 'field-agents/selfies/sample.jpg',
            'password' => Hash::make('password123'),
            'status' => FieldAgentApplicationStatus::Pending->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => FieldAgentApplicationStatus::Pending->value]);
    }

    public function underReview(): static
    {
        return $this->state(['status' => FieldAgentApplicationStatus::UnderReview->value]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => FieldAgentApplicationStatus::Approved->value,
            'reviewed_at' => now(),
            'password' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => FieldAgentApplicationStatus::Rejected->value,
            'reviewed_at' => now(),
            'rejection_reason' => 'Documents unclear. Please resubmit.',
        ]);
    }
}
```

- [ ] **Step 4: Write unit tests `tests/Unit/FieldAgentApplicationTest.php`**

```php
<?php

namespace Tests\Unit;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_pending_application(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertSame(FieldAgentApplicationStatus::Pending, $app->status);
        $this->assertNotNull($app->password);
    }

    public function test_can_be_reviewed_when_pending_or_under_review(): void
    {
        $pending = FieldAgentApplication::factory()->pending()->create();
        $underReview = FieldAgentApplication::factory()->underReview()->create();
        $approved = FieldAgentApplication::factory()->approved()->create();
        $rejected = FieldAgentApplication::factory()->rejected()->create();

        $this->assertTrue($pending->canBeReviewed());
        $this->assertTrue($underReview->canBeReviewed());
        $this->assertFalse($approved->canBeReviewed());
        $this->assertFalse($rejected->canBeReviewed());
    }

    public function test_route_notification_for_sms_returns_contact_number(): void
    {
        $app = FieldAgentApplication::factory()->create(['contact_number' => '+233555123456']);

        $this->assertSame('+233555123456', $app->routeNotificationForSms());
    }

    public function test_full_name_concatenates_first_and_last(): void
    {
        $app = FieldAgentApplication::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
        ]);

        $this->assertSame('Kofi Mensah', $app->fullName());
    }

    public function test_password_hidden_from_array(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertArrayNotHasKey('password', $app->toArray());
    }
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=FieldAgentApplicationTest`
Expected: 5 passing.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/FieldAgentApplication.php database/factories/FieldAgentApplicationFactory.php tests/Unit/FieldAgentApplicationTest.php
git commit -m "feat(field-agents): add FieldAgentApplication model with factory and tests"
```

---

## Task 6: Regions lookup endpoint (public)

**Files:**
- Create: `app/Http/Controllers/RegionLookupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FieldAgentRegistrationTest.php` (stub)

- [ ] **Step 1: Create the test stub**

Create `tests/Feature/FieldAgentRegistrationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_endpoint_returns_regions_with_cities(): void
    {
        $region = Region::factory()->create(['name' => 'Greater Accra']);
        $region->cities()->create(['name' => 'Accra', 'slug' => 'accra']);
        $region->cities()->create(['name' => 'Tema', 'slug' => 'tema']);

        $response = $this->getJson('/field-agents/regions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'cities' => ['*' => ['id', 'name', 'slug']]],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Greater Accra')
            ->assertJsonCount(2, 'data.0.cities');
    }
}
```

- [ ] **Step 2: Verify test fails**

Run: `php artisan test --compact --filter=test_regions_endpoint_returns_regions_with_cities`
Expected: FAIL (route not defined → 404).

- [ ] **Step 3: Create controller**

Create `app/Http/Controllers/RegionLookupController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\JsonResponse;

class RegionLookupController extends Controller
{
    public function index(): JsonResponse
    {
        $regions = Region::with(['cities' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'cities' => $r->cities->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])->values(),
            ]);

        return response()->json(['data' => $regions]);
    }
}
```

- [ ] **Step 4: Register route**

In `routes/web.php`, near the top (before any auth-guarded groups), add:

```php
use App\Http\Controllers\RegionLookupController;

Route::get('field-agents/regions', [RegionLookupController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('field-agents.regions');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=test_regions_endpoint_returns_regions_with_cities`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/RegionLookupController.php routes/web.php tests/Feature/FieldAgentRegistrationTest.php
git commit -m "feat(field-agents): add public regions+cities lookup endpoint"
```

---

## Task 7: `StoreFieldAgentApplicationRequest` form request

**Files:**
- Create: `app/Http/Requests/StoreFieldAgentApplicationRequest.php`

- [ ] **Step 1: Generate form request**

Run: `php artisan make:request StoreFieldAgentApplicationRequest --no-interaction`

- [ ] **Step 2: Write `app/Http/Requests/StoreFieldAgentApplicationRequest.php`**

```php
<?php

namespace App\Http\Requests;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreFieldAgentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ghana_card_number' => strtoupper(trim((string) $this->ghana_card_number)),
            'contact_number' => $this->normalizePhone((string) $this->contact_number),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required', 'email:rfc,dns', 'max:120',
                Rule::unique('users', 'email'),
                Rule::unique('field_agent_applications', 'email')
                    ->whereIn('status', FieldAgentApplicationStatus::nonRejectedStatuses()),
            ],
            'contact_number' => ['required', 'regex:/^\+233\d{9}$/'],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'city_id' => [
                'required', 'integer',
                Rule::exists('cities', 'id')->where(fn ($q) => $q->where('region_id', $this->integer('region_id'))),
            ],
            'location' => ['required', 'string', 'max:160'],
            'ghana_card_number' => [
                'required', 'regex:/^GHA-\d{9}-\d$/',
                Rule::unique('field_agent_applications', 'ghana_card_number')
                    ->whereIn('status', FieldAgentApplicationStatus::nonRejectedStatuses()),
            ],
            'ghana_card_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'selfie' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'website' => ['nullable', 'max:0'], // honeypot: must be empty
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Please enter a valid Ghana phone number (e.g. 0551234567 or +233551234567).',
            'ghana_card_number.regex' => 'Ghana card number must be in the format GHA-XXXXXXXXX-X.',
            'city_id.exists' => 'Please choose a city that belongs to the selected region.',
            'website.max' => 'Spam detected.',
        ];
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233' . substr($digits, 1);
        }

        return $raw;
    }
}
```

- [ ] **Step 3: Commit (tests come in Task 9)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreFieldAgentApplicationRequest.php
git commit -m "feat(field-agents): add form request for application submission"
```

---

## Task 8: `FieldAgentApplicationService` (create)

**Files:**
- Create: `app/Services/FieldAgentApplicationService.php`
- Create: `app/Notifications/FieldAgentApplicationReceivedNotification.php`

- [ ] **Step 1: Create the notification**

Run: `php artisan make:notification FieldAgentApplicationReceivedNotification --no-interaction`

Write `app/Notifications/FieldAgentApplicationReceivedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldAgentApplicationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FieldAgentApplication $application) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your field agent application')
            ->greeting('Hi ' . $this->application->first_name . ',')
            ->line('Thanks for applying to become a Surprise Moi field agent.')
            ->line('Our team will review your application and get back to you shortly.')
            ->line('You will receive another message (email and SMS) once the review is complete.');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->content('Surprise Moi: We received your field agent application. We will notify you once reviewed.');
    }
}
```

- [ ] **Step 2: Create service**

Create `app/Services/FieldAgentApplicationService.php`:

```php
<?php

namespace App\Services;

use App\Models\FieldAgentApplication;
use App\Notifications\FieldAgentApplicationReceivedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class FieldAgentApplicationService
{
    /**
     * @param  array<string, mixed>  $validated  Output of StoreFieldAgentApplicationRequest::validated()
     * @param  array{ghana_card_image: UploadedFile, selfie: UploadedFile}  $files
     */
    public function create(array $validated, array $files): FieldAgentApplication
    {
        return DB::transaction(function () use ($validated, $files) {
            $ghanaCardPath = $files['ghana_card_image']->store('field-agents/ghana-cards', 'public');
            $selfiePath = $files['selfie']->store('field-agents/selfies', 'public');

            $application = FieldAgentApplication::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => strtolower($validated['email']),
                'contact_number' => $validated['contact_number'],
                'region_id' => $validated['region_id'],
                'city_id' => $validated['city_id'],
                'location' => $validated['location'],
                'ghana_card_number' => $validated['ghana_card_number'],
                'ghana_card_image_path' => $ghanaCardPath,
                'selfie_path' => $selfiePath,
                'password' => Hash::make($validated['password']),
                'status' => 'pending',
            ]);

            Notification::send($application, new FieldAgentApplicationReceivedNotification($application));

            return $application;
        });
    }
}
```

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/FieldAgentApplicationService.php app/Notifications/FieldAgentApplicationReceivedNotification.php
git commit -m "feat(field-agents): add application service and received notification"
```

---

## Task 9: `FieldAgentRegistrationController` + routes + registration tests

**Files:**
- Create: `app/Http/Controllers/FieldAgentRegistrationController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/FieldAgentRegistrationTest.php`

- [ ] **Step 1: Extend the feature test with the full registration suite**

Replace contents of `tests/Feature/FieldAgentRegistrationTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\FieldAgentApplication;
use App\Models\Region;
use App\Models\User;
use App\Notifications\FieldAgentApplicationReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FieldAgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = Region::factory()->create(['name' => 'Greater Accra']);
        $this->city = City::factory()->create(['region_id' => $this->region->id, 'name' => 'Accra']);
        Storage::fake('public');
        Notification::fake();
    }

    public function test_registration_page_loads_for_guests(): void
    {
        $this->get('/field-agents/register')->assertOk();
    }

    public function test_regions_endpoint_returns_regions_with_cities(): void
    {
        $response = $this->getJson('/field-agents/regions');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'slug', 'cities']]])
            ->assertJsonPath('data.0.name', 'Greater Accra');
    }

    public function test_valid_submission_creates_application_and_stores_files(): void
    {
        $payload = $this->validPayload();

        $response = $this->post('/field-agents/register', $payload);

        $response->assertRedirect('/field-agents/register/submitted');

        $app = FieldAgentApplication::where('email', 'kofi@example.com')->first();
        $this->assertNotNull($app);
        $this->assertSame('pending', $app->status->value);
        $this->assertTrue(Hash::check('SuperSecret123', $app->password));
        Storage::disk('public')->assertExists($app->ghana_card_image_path);
        Storage::disk('public')->assertExists($app->selfie_path);

        Notification::assertSentTo($app, FieldAgentApplicationReceivedNotification::class);
    }

    public function test_password_is_hashed_not_plaintext(): void
    {
        $this->post('/field-agents/register', $this->validPayload());
        $app = FieldAgentApplication::firstOrFail();

        $this->assertNotSame('SuperSecret123', $app->password);
    }

    public function test_honeypot_blocks_silently(): void
    {
        $payload = $this->validPayload(['website' => 'http://spam.example.com']);

        $response = $this->post('/field-agents/register', $payload);

        $response->assertSessionHasErrors('website');
        $this->assertSame(0, FieldAgentApplication::count());
    }

    public function test_rate_limit_blocks_after_5_submissions(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/field-agents/register', $this->validPayload(['email' => "user{$i}@ex.com"]));
        }

        $response = $this->post('/field-agents/register', $this->validPayload(['email' => 'overflow@ex.com']));

        $response->assertStatus(429);
    }

    public function test_validation_rejects_each_missing_required_field(): void
    {
        foreach (['first_name', 'last_name', 'email', 'contact_number', 'region_id', 'city_id', 'location', 'ghana_card_number', 'ghana_card_image', 'selfie', 'password'] as $field) {
            $payload = $this->validPayload();
            unset($payload[$field]);

            $this->post('/field-agents/register', $payload)->assertSessionHasErrors($field);
        }
    }

    public function test_validation_rejects_invalid_ghana_card_format(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['ghana_card_number' => 'ABC-123']))
            ->assertSessionHasErrors('ghana_card_number');
    }

    public function test_validation_rejects_invalid_phone_format(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['contact_number' => '12345']))
            ->assertSessionHasErrors('contact_number');
    }

    public function test_validation_rejects_non_image_upload(): void
    {
        $payload = $this->validPayload(['ghana_card_image' => UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf')]);

        $this->post('/field-agents/register', $payload)->assertSessionHasErrors('ghana_card_image');
    }

    public function test_validation_rejects_oversized_upload(): void
    {
        $payload = $this->validPayload(['selfie' => UploadedFile::fake()->image('big.jpg')->size(6000)]);

        $this->post('/field-agents/register', $payload)->assertSessionHasErrors('selfie');
    }

    public function test_validation_rejects_city_from_wrong_region(): void
    {
        $otherRegion = Region::factory()->create();
        $foreignCity = City::factory()->create(['region_id' => $otherRegion->id]);

        $this->post('/field-agents/register', $this->validPayload(['city_id' => $foreignCity->id]))
            ->assertSessionHasErrors('city_id');
    }

    public function test_validation_rejects_email_that_exists_on_users_table(): void
    {
        User::factory()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertSessionHasErrors('email');
    }

    public function test_validation_rejects_duplicate_pending_email(): void
    {
        FieldAgentApplication::factory()->pending()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertSessionHasErrors('email');
    }

    public function test_rejected_application_does_not_block_resubmission(): void
    {
        FieldAgentApplication::factory()->rejected()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertRedirect('/field-agents/register/submitted');
    }

    public function test_duplicate_ghana_card_against_non_rejected_blocked(): void
    {
        FieldAgentApplication::factory()->pending()->create(['ghana_card_number' => 'GHA-123456789-1']);

        $this->post('/field-agents/register', $this->validPayload(['ghana_card_number' => 'GHA-123456789-1']))
            ->assertSessionHasErrors('ghana_card_number');
    }

    /** @param  array<string, mixed>  $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'email' => 'kofi@example.com',
            'contact_number' => '0551234567',
            'region_id' => $this->region->id,
            'city_id' => $this->city->id,
            'location' => 'Osu, near Koala',
            'ghana_card_number' => 'GHA-987654321-2',
            'ghana_card_image' => UploadedFile::fake()->image('ghana_card.jpg', 800, 600),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 600),
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'website' => '',
        ], $overrides);
    }
}
```

- [ ] **Step 2: Create controller**

Create `app/Http/Controllers/FieldAgentRegistrationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFieldAgentApplicationRequest;
use App\Models\Region;
use App\Services\FieldAgentApplicationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FieldAgentRegistrationController extends Controller
{
    public function __construct(private FieldAgentApplicationService $service) {}

    public function create(): Response
    {
        return Inertia::render('field-agent/register/index', [
            'regions' => Region::with(['cities' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreFieldAgentApplicationRequest $request): RedirectResponse
    {
        $this->service->create(
            $request->safe()->except(['ghana_card_image', 'selfie', 'website', 'password_confirmation']),
            [
                'ghana_card_image' => $request->file('ghana_card_image'),
                'selfie' => $request->file('selfie'),
            ]
        );

        return redirect()->route('field-agents.register.submitted');
    }

    public function submitted(): Response
    {
        return Inertia::render('field-agent/register/submitted');
    }
}
```

- [ ] **Step 3: Register routes**

In `routes/web.php`, add near the top (before auth-guarded groups), next to the regions route added in Task 6:

```php
use App\Http\Controllers\FieldAgentRegistrationController;

Route::prefix('field-agents')->name('field-agents.')->group(function () {
    Route::get('register', [FieldAgentRegistrationController::class, 'create'])->name('register');
    Route::post('register', [FieldAgentRegistrationController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('register.store');
    Route::get('register/submitted', [FieldAgentRegistrationController::class, 'submitted'])->name('register.submitted');
});
```

(The existing `field-agents/regions` route from Task 6 can stay as it is, or be merged into this group — engineer's choice, but keep the throttle on regions.)

- [ ] **Step 4: Create minimal placeholder Inertia pages**

The wizard page and submitted page will be fleshed out in Tasks 18-19. For now, create stubs so the tests don't fail on `Inertia::render`:

Create `resources/js/pages/field-agent/register/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';

export default function FieldAgentRegister() {
    return (
        <>
            <Head title="Become a field agent" />
            <div>Wizard placeholder</div>
        </>
    );
}
```

Create `resources/js/pages/field-agent/register/submitted.tsx`:

```tsx
import { Head } from '@inertiajs/react';

export default function FieldAgentRegisterSubmitted() {
    return (
        <>
            <Head title="Application submitted" />
            <div>Thanks — we'll be in touch.</div>
        </>
    );
}
```

- [ ] **Step 5: Run the full feature test**

Run: `php artisan test --compact --filter=FieldAgentRegistrationTest`
Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/FieldAgentRegistrationController.php routes/web.php resources/js/pages/field-agent/register/ tests/Feature/FieldAgentRegistrationTest.php
git commit -m "feat(field-agents): public registration controller, routes, and feature tests"
```

---

## Task 10: Approved + Rejected notifications

**Files:**
- Create: `app/Notifications/FieldAgentApprovedNotification.php`
- Create: `app/Notifications/FieldAgentRejectedNotification.php`

- [ ] **Step 1: Create approved notification**

Run: `php artisan make:notification FieldAgentApprovedNotification --no-interaction`

Write `app/Notifications/FieldAgentApprovedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldAgentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FieldAgentApplication $application) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your field agent application is approved')
            ->greeting('Hi ' . $this->application->first_name . ',')
            ->line('Congratulations — your field agent application has been approved.')
            ->line('You can now sign in using the email and password you provided at registration.')
            ->action('Sign in', url('/login'));
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->content('Surprise Moi: Your field agent application is approved. Sign in at ' . url('/login'));
    }
}
```

- [ ] **Step 2: Create rejected notification**

Run: `php artisan make:notification FieldAgentRejectedNotification --no-interaction`

Write `app/Notifications/FieldAgentRejectedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\FieldAgentApplication;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FieldAgentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FieldAgentApplication $application) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on your field agent application')
            ->greeting('Hi ' . $this->application->first_name . ',')
            ->line('We were unable to approve your field agent application.')
            ->line('Reason: ' . ($this->application->rejection_reason ?? 'Not specified.'))
            ->line('You are welcome to reapply once the issue is addressed.');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $reason = Str::limit((string) $this->application->rejection_reason, 80, '');

        return (new SmsMessage)
            ->content('Surprise Moi: Your field agent application was not approved. ' . $reason);
    }
}
```

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/FieldAgentApprovedNotification.php app/Notifications/FieldAgentRejectedNotification.php
git commit -m "feat(field-agents): add approved and rejected notifications"
```

---

## Task 11: `FieldAgentApprovalService` (approve + reject)

**Files:**
- Create: `app/Services/FieldAgentApprovalService.php`

- [ ] **Step 1: Create service**

Create `app/Services/FieldAgentApprovalService.php`:

```php
<?php

namespace App\Services;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use App\Models\User;
use App\Notifications\FieldAgentApprovedNotification;
use App\Notifications\FieldAgentRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class FieldAgentApprovalService
{
    public function approve(FieldAgentApplication $application, User $admin): User
    {
        if (! $application->canBeReviewed()) {
            throw new RuntimeException('Application cannot be reviewed in its current state.');
        }

        return DB::transaction(function () use ($application, $admin) {
            $user = User::create([
                'name' => $application->fullName(),
                'email' => $application->email,
                'password' => $application->password,
                'role' => 'field_agent',
                'phone' => $application->contact_number,
                'email_verified_at' => now(),
            ]);

            $application->update([
                'status' => FieldAgentApplicationStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'approved_user_id' => $user->id,
                'password' => null,
                'rejection_reason' => null,
            ]);

            Notification::send($application->fresh(), new FieldAgentApprovedNotification($application));

            return $user;
        });
    }

    public function reject(FieldAgentApplication $application, User $admin, string $reason): void
    {
        if (! $application->canBeReviewed()) {
            throw new RuntimeException('Application cannot be reviewed in its current state.');
        }

        DB::transaction(function () use ($application, $admin, $reason) {
            $application->update([
                'status' => FieldAgentApplicationStatus::Rejected->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            Notification::send($application->fresh(), new FieldAgentRejectedNotification($application));
        });
    }
}
```

**Important:** Verify the `User` model accepts `phone` in its `$fillable`. If it doesn't, look at `app/Models/User.php` and either add `phone` to fillable (if the column exists) or remove the `phone` line from the `User::create` call. (The User model has a `phone` attribute per the exploration's SmsChannel snippet.)

- [ ] **Step 2: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/FieldAgentApprovalService.php
git commit -m "feat(field-agents): add approval service (approve + reject)"
```

---

## Task 12: `RejectFieldAgentApplicationRequest`

**Files:**
- Create: `app/Http/Requests/RejectFieldAgentApplicationRequest.php`

- [ ] **Step 1: Generate form request**

Run: `php artisan make:request RejectFieldAgentApplicationRequest --no-interaction`

- [ ] **Step 2: Write the class**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectFieldAgentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && method_exists($this->user(), 'canAccessDashboard')
            && $this->user()->canAccessDashboard();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/RejectFieldAgentApplicationRequest.php
git commit -m "feat(field-agents): add rejection form request"
```

---

## Task 13: `Admin\FieldAgentApplicationController` + routes + admin tests

**Files:**
- Create: `app/Http/Controllers/Admin/FieldAgentApplicationController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`
- Create placeholder: `resources/js/pages/admin/field-agent-applications/index.tsx`
- Create placeholder: `resources/js/pages/admin/field-agent-applications/show.tsx`

- [ ] **Step 1: Write the full admin feature test first**

Create `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\FieldAgentApplication;
use App\Models\User;
use App\Notifications\FieldAgentApprovedNotification;
use App\Notifications\FieldAgentRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FieldAgentApplicationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get('/admin/field-agent-applications')
            ->assertRedirect(); // dashboard middleware redirects customers to their dashboard
    }

    public function test_admin_index_lists_applications(): void
    {
        FieldAgentApplication::factory()->count(3)->pending()->create();

        $this->actingAs($this->admin)
            ->get('/admin/field-agent-applications')
            ->assertOk();
    }

    public function test_admin_can_filter_by_status(): void
    {
        FieldAgentApplication::factory()->count(2)->pending()->create();
        FieldAgentApplication::factory()->count(1)->approved()->create();

        $this->actingAs($this->admin)
            ->get('/admin/field-agent-applications?status=pending')
            ->assertOk();
    }

    public function test_admin_can_view_application_detail(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->get("/admin/field-agent-applications/{$app->id}")
            ->assertOk();
    }

    public function test_approval_creates_field_agent_user_and_clears_password(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create([
            'email' => 'newagent@example.com',
            'password' => Hash::make('AgentSecret1'),
        ]);
        $originalHash = $app->password;

        $this->actingAs($this->admin)
            ->post("/admin/field-agent-applications/{$app->id}/approve")
            ->assertRedirect();

        $user = User::where('email', 'newagent@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('field_agent', $user->role);
        $this->assertTrue(Hash::check('AgentSecret1', $user->password));
        $this->assertSame($originalHash, $user->password);

        $app->refresh();
        $this->assertSame('approved', $app->status->value);
        $this->assertSame($this->admin->id, $app->reviewed_by);
        $this->assertNotNull($app->reviewed_at);
        $this->assertSame($user->id, $app->approved_user_id);
        $this->assertNull($app->password);

        Notification::assertSentTo([$app->fresh()], FieldAgentApprovedNotification::class);
    }

    public function test_rejection_requires_reason(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->post("/admin/field-agent-applications/{$app->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $app->refresh();
        $this->assertSame('pending', $app->status->value);
    }

    public function test_rejection_updates_application_and_sends_notification(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->post("/admin/field-agent-applications/{$app->id}/reject", [
                'rejection_reason' => 'Ghana card image is blurry.',
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('rejected', $app->status->value);
        $this->assertSame('Ghana card image is blurry.', $app->rejection_reason);
        $this->assertSame($this->admin->id, $app->reviewed_by);

        Notification::assertSentTo([$app->fresh()], FieldAgentRejectedNotification::class);
    }

    public function test_cannot_approve_already_reviewed_application(): void
    {
        $app = FieldAgentApplication::factory()->approved()->create();

        $response = $this->actingAs($this->admin)
            ->post("/admin/field-agent-applications/{$app->id}/approve");

        $response->assertSessionHasErrors();
    }

    public function test_cannot_reject_already_reviewed_application(): void
    {
        $app = FieldAgentApplication::factory()->rejected()->create();

        $response = $this->actingAs($this->admin)
            ->post("/admin/field-agent-applications/{$app->id}/reject", [
                'rejection_reason' => 'Another reason here.',
            ]);

        $response->assertSessionHasErrors();
    }
}
```

- [ ] **Step 2: Verify tests fail**

Run: `php artisan test --compact --filter=FieldAgentApplicationAdminTest`
Expected: FAIL (routes/controllers not defined).

- [ ] **Step 3: Create placeholder Inertia pages**

Create `resources/js/pages/admin/field-agent-applications/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';

export default function FieldAgentApplicationsIndex() {
    return (<><Head title="Field Agent Applications" /><div>Admin index placeholder</div></>);
}
```

Create `resources/js/pages/admin/field-agent-applications/show.tsx`:

```tsx
import { Head } from '@inertiajs/react';

export default function FieldAgentApplicationShow() {
    return (<><Head title="Application detail" /><div>Admin detail placeholder</div></>);
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/FieldAgentApplicationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FieldAgentApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectFieldAgentApplicationRequest;
use App\Models\FieldAgentApplication;
use App\Services\FieldAgentApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FieldAgentApplicationController extends Controller
{
    public function __construct(private FieldAgentApprovalService $service) {}

    public function index(Request $request): Response
    {
        $query = FieldAgentApplication::query()
            ->with(['region:id,name', 'city:id,name', 'reviewer:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($regionId = $request->integer('region_id')) {
            $query->where('region_id', $regionId);
        }

        $applications = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/field-agent-applications/index', [
            'applications' => $applications,
            'filters' => $request->only(['status', 'region_id']),
            'statuses' => collect(FieldAgentApplicationStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(FieldAgentApplication $fieldAgentApplication): Response
    {
        $fieldAgentApplication->load(['region:id,name', 'city:id,name', 'reviewer:id,name', 'approvedUser:id,name,email']);

        return Inertia::render('admin/field-agent-applications/show', [
            'application' => array_merge($fieldAgentApplication->toArray(), [
                'ghana_card_image_url' => \Storage::disk('public')->url($fieldAgentApplication->ghana_card_image_path),
                'selfie_url' => \Storage::disk('public')->url($fieldAgentApplication->selfie_path),
            ]),
        ]);
    }

    public function approve(Request $request, FieldAgentApplication $fieldAgentApplication): RedirectResponse
    {
        if (! $fieldAgentApplication->canBeReviewed()) {
            throw ValidationException::withMessages([
                'status' => 'This application has already been reviewed.',
            ]);
        }

        try {
            $this->service->approve($fieldAgentApplication, $request->user());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.field-agent-applications.show', $fieldAgentApplication)
            ->with('success', 'Field agent approved.');
    }

    public function reject(RejectFieldAgentApplicationRequest $request, FieldAgentApplication $fieldAgentApplication): RedirectResponse
    {
        if (! $fieldAgentApplication->canBeReviewed()) {
            throw ValidationException::withMessages([
                'status' => 'This application has already been reviewed.',
            ]);
        }

        try {
            $this->service->reject($fieldAgentApplication, $request->user(), $request->string('rejection_reason')->toString());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.field-agent-applications.show', $fieldAgentApplication)
            ->with('success', 'Application rejected.');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, inside the existing `Route::middleware(['auth', 'dashboard'])...` group (look for the group near line 71 that contains vendor-applications), add:

```php
use App\Http\Controllers\Admin\FieldAgentApplicationController;

Route::prefix('admin/field-agent-applications')->name('admin.field-agent-applications.')->group(function () {
    Route::get('/', [FieldAgentApplicationController::class, 'index'])->name('index');
    Route::get('/{fieldAgentApplication}', [FieldAgentApplicationController::class, 'show'])->name('show');
    Route::post('/{fieldAgentApplication}/approve', [FieldAgentApplicationController::class, 'approve'])->name('approve');
    Route::post('/{fieldAgentApplication}/reject', [FieldAgentApplicationController::class, 'reject'])->name('reject');
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=FieldAgentApplicationAdminTest`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/FieldAgentApplicationController.php routes/web.php resources/js/pages/admin/field-agent-applications/ tests/Feature/Admin/FieldAgentApplicationAdminTest.php
git commit -m "feat(field-agents): admin controller for reviewing applications + tests"
```

---

## Task 14: Fortify login status check (account-enumeration-safe)

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`
- Create: `tests/Feature/FieldAgentLoginFlowTest.php`

- [ ] **Step 1: Write the login flow feature test**

Create `tests/Feature/FieldAgentLoginFlowTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\FieldAgentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FieldAgentLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_field_agent_logs_in_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'field_agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('AgentPass1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'agent@example.com',
            'password' => 'AgentPass1',
        ]);

        $response->assertRedirect('/field-agent/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_applicant_with_correct_password_sees_under_review_message(): void
    {
        FieldAgentApplication::factory()->pending()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'Correct1',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('under review', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }

    public function test_rejected_applicant_with_correct_password_sees_not_approved_message(): void
    {
        FieldAgentApplication::factory()->rejected()->create([
            'email' => 'rejected@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        // Factory's rejected() nulls out reviewed_at but not password — but our approve() nulls password.
        // For rejected, the password is still present (no approval happened), so this test path works.
        // If factory changed, ensure password is preserved on rejected state.

        $response = $this->post('/login', [
            'email' => 'rejected@example.com',
            'password' => 'Correct1',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('not approved', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }

    public function test_wrong_password_on_application_returns_generic_error(): void
    {
        FieldAgentApplication::factory()->pending()->create([
            'email' => 'pending2@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'pending2@example.com',
            'password' => 'WrongPass!',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringNotContainsString('under review', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }
}
```

**Note before writing implementation:** Verify the `FieldAgentApplicationFactory::rejected()` state preserves `password`. The factory above does preserve it (rejected state only overrides status/reviewed_at/rejection_reason). Good.

- [ ] **Step 2: Modify `app/Providers/FortifyServiceProvider.php`**

In the `configureActions()` method, add `authenticateUsing`:

```php
use App\Models\FieldAgentApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

private function configureActions(): void
{
    Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    Fortify::createUsersUsing(CreateNewUser::class);

    Fortify::authenticateUsing(function (Request $request) {
        $user = \App\Models\User::where('email', $request->input('email'))->first();

        if ($user && Hash::check((string) $request->input('password'), $user->password)) {
            return $user;
        }

        // Check for a matching field-agent application with the same password hash
        $application = FieldAgentApplication::where('email', $request->input('email'))
            ->whereNotNull('password')
            ->first();

        if ($application && Hash::check((string) $request->input('password'), $application->password)) {
            $message = match ($application->status->value) {
                'pending', 'under_review' => 'Your field agent application is under review. We will notify you once approved.',
                'rejected' => 'Your field agent application was not approved. ' . ($application->rejection_reason ? 'Reason: ' . $application->rejection_reason : ''),
                default => 'Invalid credentials.',
            };

            throw ValidationException::withMessages(['email' => $message]);
        }

        return null; // Fortify will surface the default "invalid credentials" error
    });
}
```

- [ ] **Step 3: Add login redirect for field_agent role**

Still in `FortifyServiceProvider::boot()`, add (after `configureRateLimiting();`):

```php
\Laravel\Fortify\Fortify::redirects('login', function ($request) {
    $user = $request->user();
    return match ($user?->role) {
        'field_agent' => route('field-agent.dashboard'),
        default => null, // Let the default behavior handle other roles
    };
});
```

**Verify existing redirect config first:** open the file fully and check whether other roles already have redirect overrides. If so, add the `field_agent` branch inside that existing callback. Do not remove any existing role branches.

- [ ] **Step 4: Run login flow tests**

Run: `php artisan test --compact --filter=FieldAgentLoginFlowTest`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/FortifyServiceProvider.php tests/Feature/FieldAgentLoginFlowTest.php
git commit -m "feat(field-agents): login shows status for pending/rejected applicants"
```

---

## Task 15: Access restriction for field agents (extend `EnsureDashboardAccess`)

**Files:**
- Modify: `app/Http/Middleware/EnsureDashboardAccess.php`
- Create: `tests/Feature/FieldAgentAccessRestrictionTest.php`

**Context:** `EnsureDashboardAccess` already redirects field_agent away from non-field-agent dashboard routes. We need to ensure:
1. It doesn't fight profile/password/logout routes (these should continue to work for field agents).
2. Our tests verify the expected routing behavior.

In practice the existing middleware redirects based on `$currentPath` not starting with `field-agent`. If `profile`, `password`, and `logout` routes are NOT under the `dashboard` middleware (check the routes file), the existing middleware won't intercept them. If they ARE, we need a whitelist.

- [ ] **Step 1: Inspect `routes/web.php` and the current middleware**

Open `app/Http/Middleware/EnsureDashboardAccess.php` and `routes/web.php`. Identify:
- Are `profile.*` / `password.*` / `logout` routes under `dashboard` middleware? (grep for `profile` and `password` in `routes/web.php`.)
- Does the middleware's field_agent redirect prevent access to those?

Confirmation required before modifying:
Run: `grep -nE "profile|password\.|logout" routes/web.php | head -30`

- [ ] **Step 2: Write the access restriction test**

Create `tests/Feature/FieldAgentAccessRestrictionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_agent_is_redirected_away_from_admin_routes(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->actingAs($agent)
            ->get('/admin/field-agent-applications')
            ->assertRedirect('/field-agent/dashboard');
    }

    public function test_field_agent_can_access_own_dashboard(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->actingAs($agent)
            ->get('/field-agent/dashboard')
            ->assertOk();
    }

    public function test_admin_is_unaffected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/field-agent-applications')
            ->assertOk();
    }
}
```

- [ ] **Step 3: Run test and iterate on middleware**

Run: `php artisan test --compact --filter=FieldAgentAccessRestrictionTest`

If any test fails, open `app/Http/Middleware/EnsureDashboardAccess.php`. The existing code has a field_agent branch — confirm it redirects to `field-agent.dashboard` when path doesn't start with `field-agent`. If profile/password tests fail (they shouldn't be in this test, but if you add them), add a whitelist before the redirect:

```php
$whitelistedPrefixes = ['field-agent', 'profile', 'password', 'logout'];
$isAllowed = collect($whitelistedPrefixes)->some(fn ($p) => str_starts_with($currentPath, $p));

if ($user->role === 'field_agent' && ! $isAllowed) {
    return redirect()->route('field-agent.dashboard');
}
```

Only make this change if tests require it. Don't touch other role branches.

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureDashboardAccess.php tests/Feature/FieldAgentAccessRestrictionTest.php
git commit -m "feat(field-agents): fence field agents into their dashboard area"
```

---

## Task 16: Share pending-application count via Inertia

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Open and inspect the current `share()` method**

Read `app/Http/Middleware/HandleInertiaRequests.php`. Note the current return array.

- [ ] **Step 2: Add pending count to shared props**

Add (only if `auth.user` is present and is an admin — don't run the count query for non-admin requests):

```php
use App\Models\FieldAgentApplication;

public function share(Request $request): array
{
    $user = $request->user();

    $shared = parent::share($request);
    // ... existing shared props

    return array_merge($shared, [
        // ... existing
        'badges' => [
            'pending_field_agent_applications' => $user?->role === 'admin' || $user?->role === 'super_admin'
                ? FieldAgentApplication::whereIn('status', ['pending', 'under_review'])->count()
                : 0,
        ],
    ]);
}
```

Keep the shape compatible with whatever's already there. If a `badges` key already exists, nest under it; otherwise add it as above.

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(field-agents): share pending application count in inertia props"
```

---

## Task 17: Sidebar entries (admin + field-agent)

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Open the file and locate `getNavItemsForRole`**

Open `resources/js/components/app-sidebar.tsx`. Find `getNavItemsForRole` (starts around line 47 per prior exploration). Note the admin-role branch (~lines 49–191) and the field_agent branch (~lines 226–255, may be empty or sparse).

- [ ] **Step 2: Add admin sidebar item for Field Agent Applications**

Inside the admin branch, find where "Field Agents" currently appears (the existing entry points to `usersIndex().url + '?role=field_agent'`). Add a sibling entry BEFORE or AFTER it:

```tsx
{
    title: 'Field Agent Applications',
    href: '/admin/field-agent-applications',
    icon: Users, // or ClipboardList — pick whichever lucide-react icon is already imported
    badge: pageProps.badges?.pending_field_agent_applications ?? 0,
}
```

If the existing nav item type doesn't support `badge`, you'll need to extend it. Check the existing shape of NavItem/NavGroupItem. If badges aren't supported, add `badge?: number` to the type and handle rendering in the sidebar component (render `<Badge>{item.badge}</Badge>` next to the title when `item.badge > 0`).

- [ ] **Step 3: Populate the field_agent branch**

Replace the (likely sparse) field_agent return with:

```tsx
return [
    {
        title: 'Dashboard',
        href: '/field-agent/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'My Targets',
        href: '/field-agent/targets',
        icon: Target, // lucide-react; pick an existing import or add
    },
    {
        title: 'My Earnings',
        href: '/field-agent/earnings',
        icon: Wallet,
    },
    {
        title: 'Payouts',
        href: '/field-agent/payouts',
        icon: Banknote,
    },
    {
        title: 'My Verification',
        href: '/field-agent/verification',
        icon: ShieldCheck,
    },
];
```

Ensure imported icons exist; pick alternatives from the set already imported at the top of the file.

- [ ] **Step 4: Build the frontend and smoke-test manually**

Run: `pnpm run build`
Expected: no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/app-sidebar.tsx
git commit -m "feat(field-agents): sidebar entries for admin applications + field agent nav"
```

---

## Task 18: Registration wizard frontend (Steps 1-4)

**Files:**
- Modify: `resources/js/pages/field-agent/register/index.tsx`

**Context:** A single-page wizard using `useForm` from `@inertiajs/react`. Local state tracks the current step. `forceFormData: true` on submit since we have file uploads. Client-side per-step validation is light (required checks); server-side is authoritative.

- [ ] **Step 1: Write the full wizard page**

Replace `resources/js/pages/field-agent/register/index.tsx` with the following. Adjust imports/components to match the project's UI primitives (check `resources/js/components/ui/` for existing `Input`, `Label`, `Button`, `Select`, `Card`). The snippet below uses named imports — map to actual paths when writing.

```tsx
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type City = { id: number; name: string; slug: string };
type Region = { id: number; name: string; slug: string; cities: City[] };

type Props = { regions: Region[] };

type WizardData = {
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    password: string;
    password_confirmation: string;
    region_id: string;
    city_id: string;
    location: string;
    ghana_card_number: string;
    ghana_card_image: File | null;
    selfie: File | null;
    website: string;
};

const STEPS = ['Personal', 'Location', 'Identity', 'Review'] as const;

export default function FieldAgentRegister({ regions }: Props) {
    const [step, setStep] = useState<number>(0);

    const form = useForm<WizardData>({
        first_name: '',
        last_name: '',
        email: '',
        contact_number: '',
        password: '',
        password_confirmation: '',
        region_id: '',
        city_id: '',
        location: '',
        ghana_card_number: '',
        ghana_card_image: null,
        selfie: null,
        website: '',
    });

    const { data, setData, post, processing, errors } = form;

    const selectedRegion = useMemo(
        () => regions.find((r) => String(r.id) === data.region_id),
        [regions, data.region_id],
    );

    const canProceed = (): boolean => {
        if (step === 0) {
            return Boolean(data.first_name && data.last_name && data.email && data.contact_number && data.password && data.password === data.password_confirmation);
        }
        if (step === 1) {
            return Boolean(data.region_id && data.city_id && data.location);
        }
        if (step === 2) {
            return Boolean(data.ghana_card_number && data.ghana_card_image && data.selfie);
        }
        return true;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/field-agents/register', { forceFormData: true });
    };

    return (
        <>
            <Head title="Become a field agent" />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="text-2xl font-semibold">Become a Field Agent</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Step {step + 1} of {STEPS.length} — {STEPS[step]}
                </p>

                {/* progress bar */}
                <div className="my-4 flex gap-2">
                    {STEPS.map((_, i) => (
                        <div key={i} className={`h-2 flex-1 rounded-full ${i <= step ? 'bg-primary' : 'bg-muted'}`} />
                    ))}
                </div>

                <form onSubmit={submit} encType="multipart/form-data">
                    {/* hidden honeypot */}
                    <input
                        type="text"
                        name="website"
                        value={data.website}
                        onChange={(e) => setData('website', e.target.value)}
                        className="hidden"
                        tabIndex={-1}
                        autoComplete="off"
                    />

                    {step === 0 && (
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="first_name">First name</Label>
                                <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                                {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="last_name">Last name</Label>
                                <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                                {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>
                            <div>
                                <Label htmlFor="contact_number">Contact number</Label>
                                <Input id="contact_number" placeholder="0551234567" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} />
                                {errors.contact_number && <p className="text-sm text-destructive">{errors.contact_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="password">Password</Label>
                                <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                            </div>
                            <div>
                                <Label htmlFor="password_confirmation">Confirm password</Label>
                                <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <div>
                                <Label>Region</Label>
                                <Select value={data.region_id} onValueChange={(v) => { setData('region_id', v); setData('city_id', ''); }}>
                                    <SelectTrigger><SelectValue placeholder="Select a region" /></SelectTrigger>
                                    <SelectContent>
                                        {regions.map((r) => <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                {errors.region_id && <p className="text-sm text-destructive">{errors.region_id}</p>}
                            </div>
                            <div>
                                <Label>City</Label>
                                <Select value={data.city_id} onValueChange={(v) => setData('city_id', v)} disabled={!selectedRegion}>
                                    <SelectTrigger><SelectValue placeholder="Select a city" /></SelectTrigger>
                                    <SelectContent>
                                        {selectedRegion?.cities.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                {errors.city_id && <p className="text-sm text-destructive">{errors.city_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input id="location" placeholder="e.g. Osu, near Koala" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                                {errors.location && <p className="text-sm text-destructive">{errors.location}</p>}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="ghana_card_number">Ghana card number</Label>
                                <Input id="ghana_card_number" placeholder="GHA-123456789-1" value={data.ghana_card_number} onChange={(e) => setData('ghana_card_number', e.target.value)} />
                                {errors.ghana_card_number && <p className="text-sm text-destructive">{errors.ghana_card_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="ghana_card_image">Ghana card photo (max 5MB)</Label>
                                <Input id="ghana_card_image" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setData('ghana_card_image', e.target.files?.[0] ?? null)} />
                                {errors.ghana_card_image && <p className="text-sm text-destructive">{errors.ghana_card_image}</p>}
                            </div>
                            <div>
                                <Label htmlFor="selfie">Selfie (max 5MB)</Label>
                                <Input id="selfie" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setData('selfie', e.target.files?.[0] ?? null)} />
                                {errors.selfie && <p className="text-sm text-destructive">{errors.selfie}</p>}
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-2">
                            <h2 className="text-lg font-medium">Review</h2>
                            <div className="rounded border p-4 text-sm">
                                <p><strong>Name:</strong> {data.first_name} {data.last_name}</p>
                                <p><strong>Email:</strong> {data.email}</p>
                                <p><strong>Contact:</strong> {data.contact_number}</p>
                                <p><strong>Region:</strong> {selectedRegion?.name}</p>
                                <p><strong>City:</strong> {selectedRegion?.cities.find(c => String(c.id) === data.city_id)?.name}</p>
                                <p><strong>Location:</strong> {data.location}</p>
                                <p><strong>Ghana card:</strong> {data.ghana_card_number}</p>
                                <p><strong>Ghana card photo:</strong> {data.ghana_card_image?.name ?? '—'}</p>
                                <p><strong>Selfie:</strong> {data.selfie?.name ?? '—'}</p>
                            </div>
                        </div>
                    )}

                    <div className="mt-6 flex justify-between">
                        <Button type="button" variant="outline" disabled={step === 0 || processing} onClick={() => setStep(step - 1)}>Back</Button>
                        {step < STEPS.length - 1 ? (
                            <Button type="button" disabled={!canProceed() || processing} onClick={() => setStep(step + 1)}>Next</Button>
                        ) : (
                            <Button type="submit" disabled={processing}>Submit application</Button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Flesh out `submitted.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';

export default function FieldAgentRegisterSubmitted() {
    return (
        <>
            <Head title="Application received" />
            <div className="mx-auto max-w-xl p-6 text-center">
                <h1 className="text-2xl font-semibold">Application received</h1>
                <p className="mt-4 text-sm text-muted-foreground">
                    Thank you for applying to become a Surprise Moi field agent. Our team will review your submission and contact you by email and SMS once the review is complete.
                </p>
                <Link href="/" className="mt-6 inline-block underline">Back to home</Link>
            </div>
        </>
    );
}
```

- [ ] **Step 3: Build and smoke-test**

Run: `pnpm run build`
Expected: clean build. Then in dev: `pnpm run dev` (if not already running) and navigate to `/field-agents/register` in a browser. Step through each of the 4 steps with valid data. Submit. Confirm redirect to `/field-agents/register/submitted` and that a new `field_agent_applications` row exists.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/field-agent/register/
git commit -m "feat(field-agents): 4-step registration wizard UI"
```

---

## Task 19: Admin Index page

**Files:**
- Modify: `resources/js/pages/admin/field-agent-applications/index.tsx`

- [ ] **Step 1: Write the index page**

Replace the placeholder with a functional list:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    status: string;
    created_at: string;
    region?: { name: string };
    city?: { name: string };
};

type Props = {
    applications: {
        data: Application[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { status?: string; region_id?: number };
    statuses: Array<{ value: string; label: string }>;
};

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    under_review: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

export default function FieldAgentApplicationsIndex({ applications, filters, statuses }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilter = (newStatus: string) => {
        setStatus(newStatus);
        router.get('/admin/field-agent-applications', { status: newStatus || undefined }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Field Agent Applications" />
            <div className="p-6">
                <h1 className="text-2xl font-semibold">Field Agent Applications</h1>

                <div className="mt-4 flex gap-2">
                    <Select value={status} onValueChange={applyFilter}>
                        <SelectTrigger className="w-48"><SelectValue placeholder="All statuses" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All</SelectItem>
                            {statuses.map((s) => <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <div className="mt-4 rounded border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Region</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Applied</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {applications.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell>{a.first_name} {a.last_name}</TableCell>
                                    <TableCell>{a.email}</TableCell>
                                    <TableCell>{a.region?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge className={statusColors[a.status] ?? ''}>{a.status}</Badge>
                                    </TableCell>
                                    <TableCell>{new Date(a.created_at).toLocaleDateString()}</TableCell>
                                    <TableCell className="text-right">
                                        <Link href={`/admin/field-agent-applications/${a.id}`} className="text-primary underline">View</Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {applications.data.length === 0 && (
                                <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground">No applications yet.</TableCell></TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* pagination */}
                <div className="mt-4 flex gap-1">
                    {applications.links.map((l, i) => (
                        <Button key={i} variant={l.active ? 'default' : 'outline'} size="sm" disabled={!l.url}
                            onClick={() => l.url && router.get(l.url)} dangerouslySetInnerHTML={{ __html: l.label }} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build and verify**

Run: `pnpm run build`
Expected: clean build.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/admin/field-agent-applications/index.tsx
git commit -m "feat(field-agents): admin applications index page"
```

---

## Task 20: Admin Show page with approve/reject modals

**Files:**
- Modify: `resources/js/pages/admin/field-agent-applications/show.tsx`

- [ ] **Step 1: Write the show page**

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { FormEvent, useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    location: string;
    ghana_card_number: string;
    status: string;
    region?: { name: string };
    city?: { name: string };
    reviewer?: { name: string } | null;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
    ghana_card_image_url: string;
    selfie_url: string;
};

export default function FieldAgentApplicationShow({ application }: { application: Application }) {
    const [rejectOpen, setRejectOpen] = useState(false);

    const approveForm = useForm({});
    const rejectForm = useForm({ rejection_reason: '' });

    const reviewable = application.status === 'pending' || application.status === 'under_review';

    const onApprove = (e: FormEvent) => {
        e.preventDefault();
        approveForm.post(`/admin/field-agent-applications/${application.id}/approve`);
    };

    const onReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/admin/field-agent-applications/${application.id}/reject`, {
            onSuccess: () => setRejectOpen(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Application — ${application.first_name} ${application.last_name}`} />
            <div className="p-6 space-y-6 max-w-4xl">
                <div className="flex justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{application.first_name} {application.last_name}</h1>
                        <p className="text-sm text-muted-foreground">Status: {application.status}</p>
                    </div>
                    {reviewable && (
                        <div className="flex gap-2">
                            <form onSubmit={onApprove}>
                                <Button type="submit" disabled={approveForm.processing}>Approve</Button>
                            </form>
                            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">Reject</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Reject application</DialogTitle>
                                        <DialogDescription>Please provide a clear reason. This will be sent to the applicant.</DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={onReject} className="space-y-4">
                                        <Textarea
                                            rows={4}
                                            placeholder="Reason for rejection…"
                                            value={rejectForm.data.rejection_reason}
                                            onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                                        />
                                        {rejectForm.errors.rejection_reason && (
                                            <p className="text-sm text-destructive">{rejectForm.errors.rejection_reason}</p>
                                        )}
                                        <DialogFooter>
                                            <Button type="button" variant="outline" onClick={() => setRejectOpen(false)}>Cancel</Button>
                                            <Button type="submit" variant="destructive" disabled={rejectForm.processing}>Confirm rejection</Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Section title="Contact">
                        <Field label="Email" value={application.email} />
                        <Field label="Phone" value={application.contact_number} />
                    </Section>
                    <Section title="Location">
                        <Field label="Region" value={application.region?.name ?? '—'} />
                        <Field label="City" value={application.city?.name ?? '—'} />
                        <Field label="Location" value={application.location} />
                    </Section>
                    <Section title="Identity">
                        <Field label="Ghana card number" value={application.ghana_card_number} />
                    </Section>
                    <Section title="Review">
                        <Field label="Status" value={application.status} />
                        {application.reviewer && <Field label="Reviewed by" value={application.reviewer.name} />}
                        {application.reviewed_at && <Field label="Reviewed at" value={new Date(application.reviewed_at).toLocaleString()} />}
                        {application.rejection_reason && <Field label="Reason" value={application.rejection_reason} />}
                    </Section>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Figure title="Ghana card" url={application.ghana_card_image_url} />
                    <Figure title="Selfie" url={application.selfie_url} />
                </div>
            </div>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (<div className="rounded border p-4"><h2 className="font-medium mb-2">{title}</h2><div className="space-y-1 text-sm">{children}</div></div>);
}
function Field({ label, value }: { label: string; value: string }) {
    return (<div className="flex justify-between"><span className="text-muted-foreground">{label}</span><span>{value}</span></div>);
}
function Figure({ title, url }: { title: string; url: string }) {
    return (
        <div className="rounded border p-4">
            <h2 className="font-medium mb-2">{title}</h2>
            <a href={url} target="_blank" rel="noreferrer"><img src={url} alt={title} className="max-h-96 rounded" /></a>
        </div>
    );
}
```

- [ ] **Step 2: Build and manual smoke-test**

Run: `pnpm run build`
Then as admin, visit `/admin/field-agent-applications`, open one, approve it. Verify the target User row exists, then try logging in with that email+password → should land on `/field-agent/dashboard`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/admin/field-agent-applications/show.tsx
git commit -m "feat(field-agents): admin applications detail page with approve/reject"
```

---

## Task 21: Field agent verification page

**Files:**
- Create: `app/Http/Controllers/FieldAgentVerificationController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/field-agent/verification.tsx`

- [ ] **Step 1: Create controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\FieldAgentApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldAgentVerificationController extends Controller
{
    public function show(Request $request): Response
    {
        $application = FieldAgentApplication::with(['region:id,name', 'city:id,name'])
            ->where('approved_user_id', $request->user()->id)
            ->latest()
            ->first();

        return Inertia::render('field-agent/verification', [
            'application' => $application ? array_merge($application->toArray(), [
                'ghana_card_image_url' => \Storage::disk('public')->url($application->ghana_card_image_path),
                'selfie_url' => \Storage::disk('public')->url($application->selfie_path),
            ]) : null,
        ]);
    }
}
```

- [ ] **Step 2: Register route**

In `routes/web.php`, inside the existing `['auth', 'dashboard']` field-agent group:

```php
Route::get('verification', [\App\Http\Controllers\FieldAgentVerificationController::class, 'show'])->name('verification');
```

(Insert BEFORE the catch-all `field-agent/{any?}` SPA route so it's matched first.)

- [ ] **Step 3: Create the page**

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type Props = {
    application: {
        first_name: string;
        last_name: string;
        email: string;
        contact_number: string;
        location: string;
        ghana_card_number: string;
        status: string;
        region?: { name: string };
        city?: { name: string };
        ghana_card_image_url: string;
        selfie_url: string;
    } | null;
};

export default function Verification({ application }: Props) {
    if (!application) {
        return (
            <AppLayout>
                <Head title="My verification" />
                <div className="p-6">No verification record found.</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="My verification" />
            <div className="p-6 max-w-3xl space-y-4">
                <h1 className="text-2xl font-semibold">My verification</h1>
                <div className="rounded border p-4 space-y-1 text-sm">
                    <p><strong>Name:</strong> {application.first_name} {application.last_name}</p>
                    <p><strong>Email:</strong> {application.email}</p>
                    <p><strong>Phone:</strong> {application.contact_number}</p>
                    <p><strong>Region:</strong> {application.region?.name}</p>
                    <p><strong>City:</strong> {application.city?.name}</p>
                    <p><strong>Location:</strong> {application.location}</p>
                    <p><strong>Ghana card:</strong> {application.ghana_card_number}</p>
                    <p><strong>Status:</strong> {application.status}</p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded border p-4"><h2 className="font-medium mb-2">Ghana card</h2><img src={application.ghana_card_image_url} className="max-h-96 rounded" alt="Ghana card" /></div>
                    <div className="rounded border p-4"><h2 className="font-medium mb-2">Selfie</h2><img src={application.selfie_url} className="max-h-96 rounded" alt="Selfie" /></div>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Build and commit**

```bash
pnpm run build
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/FieldAgentVerificationController.php routes/web.php resources/js/pages/field-agent/verification.tsx
git commit -m "feat(field-agents): read-only verification page for approved agents"
```

---

## Task 22: Full regression sweep

**Files:** none modified; runtime checks only.

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: all green, including any pre-existing tests unaffected.

- [ ] **Step 2: Run linter one more time**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 3: Manual smoke-test end-to-end**

- Start dev: `composer run dev`
- As guest: `/field-agents/register` → complete wizard → submitted page. Check DB: a `pending` row exists. Check mail/SMS queue (or logs) for received notification.
- As admin: `/admin/field-agent-applications` → verify sidebar badge. Open application, approve. Verify User row created with role=field_agent and password matches. Verify approved notification queued.
- Log out. Log in as the new field agent. Land on `/field-agent/dashboard`. Try navigating to `/users`, `/vendors`, `/admin/field-agent-applications` — each should redirect back to `/field-agent/dashboard`.
- Visit `/field-agent/verification` — see the submitted documents.
- Log out. Create a new pending application. Try to log in with those creds: should see "under review" message.
- Reject that pending application as admin with a reason. Try to log in: should see "not approved" with reason. SMS + mail should have been queued.

- [ ] **Step 4: Final commit (if any touch-ups)**

Only commit if something needed fixing during smoke-test.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "chore(field-agents): smoke-test fixes"
```

---

## Plan Self-Review

**Spec coverage:**
- Data model (regions, cities, field_agent_applications) — Tasks 1, 2, 4, 5 ✓
- Regions seeder — Task 3 ✓
- Public registration wizard — Tasks 7, 9, 18 ✓
- Application received notification — Task 8 ✓
- Admin review + approve/reject — Tasks 11, 12, 13, 19, 20 ✓
- Approved/Rejected notifications (dual-channel) — Task 10 ✓
- Login status check (account-enum safe) — Task 14 ✓
- Login redirect by role — Task 14 ✓
- Access restriction middleware — Task 15 ✓
- Pending-count badge shared via Inertia — Task 16 ✓
- Sidebar (admin + field-agent) — Task 17 ✓
- Verification page — Task 21 ✓
- Regression sweep — Task 22 ✓
- All test categories from spec §8 — Tasks 9 (registration), 13 (admin), 14 (login), 15 (access), 5 (unit) ✓

**Placeholder scan:** no TBD/TODO found. All steps contain concrete code.

**Type consistency:** `FieldAgentApplicationStatus` enum values used consistently (`pending`, `under_review`, `approved`, `rejected`). Service method names consistent (`approve`, `reject`). Path conventions consistent (`field-agents.*` for public, `field-agent.*` for authenticated).

**Known unknowns flagged inline in tasks:**
- Task 11: Verify `User` model has `phone` in fillable before relying on it (engineer instructed to check).
- Task 14 Step 3: Verify existing Fortify redirect config before adding (engineer instructed to check).
- Task 15 Step 1: Verify profile/password routes placement vs. dashboard middleware (engineer instructed to check).
- Task 16 Step 1: Match existing badges key shape if one exists (engineer instructed to check).
- Task 17 Step 2: Verify NavItem type supports `badge` (engineer instructed to check).

These are intentional — they prevent speculative code in the plan and keep the engineer honest about reality.
