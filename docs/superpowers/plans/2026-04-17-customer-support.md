# Customer Support Console Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a unified Customer Support console at `/dashboard/customer-support` for admins. Lets CSRs track tickets, log every interaction, send outbound SMS to contacts from the same page, view follow-ups due, and surface upcoming birthdays.

**Architecture:**
- Three new tables: `support_tickets` (the case), `support_interactions` (chronological log per ticket), `support_messages` (single source of truth for outbound SMS).
- Backend: four controllers under `App\Http\Controllers\Admin\` plus one queued `SendSupportSmsJob` that calls the existing `SmsProviderInterface` (already used by Field Agent notifications).
- Frontend: Inertia pages under `resources/js/Pages/admin/customer-support/` (`index`, `create`, `show`) using existing shadcn primitives. The index page is a 3-tab layout (Tickets / Messaging / Birthdays).
- Permissions: routes mount inside the existing `['auth', 'dashboard']` middleware group; access is admin/super_admin via `EnsureDashboardAccess`. No new role for v1.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind, PHPUnit 11, Horizon v5 (Redis queue), Wayfinder. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-04-17-customer-support-design.md`

**Branch:** `feat/customer-support` (already created, spec committed at `c5de746`).

**Important context the engineer should know before starting:**

- Admin web routes mount at URL prefix `/dashboard/...` and name prefix `dashboard....`, inside `Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(...)` in `routes/web.php` (around line 83).
- The `dashboard` middleware alias resolves to `EnsureDashboardAccess`. It already gates non-admin roles. No new middleware needed.
- SMS is already wired. The contract is `App\Contracts\Sms\SmsProviderInterface`, default binding is `App\Services\KairosAfrikaSmsService`. Confirm the exact method signature when implementing the job (likely `send(string $to, string $body)`). Reference: how `App\Notifications\Messages\SmsMessage` and `App\Channels\SmsChannel` are used by Field Agent notifications.
- Form Requests: array-style rules with a `messages()` method. Reference: `app/Http/Requests/ResolveReportRequest.php`.
- Inertia controller pattern: `Inertia::render('admin/<feature>/<page>', [...])`. Pages live at `resources/js/Pages/admin/<feature>/<page>.tsx`. Reference: `app/Http/Controllers/Admin/FieldAgentApplicationController.php` + `resources/js/Pages/admin/field-agent-applications/`.
- Feature tests: PHPUnit (not Pest), `RefreshDatabase`, `Notification::fake()` / `Queue::fake()`, factory users with `['role' => 'admin']`, `actingAs()`. Reference: `tests/Feature/Admin/FieldAgentApplicationAdminTest.php`. Live in `tests/Feature/CustomerSupport/`.
- Sidebar lives at `resources/js/components/app-sidebar.tsx`. Add the new entry inside the `'Support'` group (currently has only "Reports & Conflicts").
- After any route change, run `php artisan wayfinder:generate` so the typed actions in `resources/js/actions/` and `resources/js/routes/` regenerate.
- After finalizing changes in any task: run `vendor/bin/pint --dirty --format agent` before committing.
- Run scoped tests with `php artisan test --compact --filter=<TestNameOrFilter>`.
- Phone numbers are stored in `+233XXXXXXXXX` form. Confirm the helper used by `KairosAfrikaSmsService` (likely a method on the service or a normalizer in `app/Support/`) and reuse it; do NOT roll a new one.
- We are using Docker locally and in production. Run artisan/php commands via your normal `composer`/`./vendor` invocation — Docker exec wrapping is the dev's responsibility.

---

## Task 1: Create the three migrations

**Files:**
- Create: `database/migrations/2026_04_17_120000_create_support_tickets_table.php`
- Create: `database/migrations/2026_04_17_120001_create_support_interactions_table.php`
- Create: `database/migrations/2026_04_17_120002_create_support_messages_table.php`

- [ ] **Step 1: Generate the three migration files**

Run:
```
php artisan make:migration create_support_tickets_table --no-interaction
php artisan make:migration create_support_interactions_table --no-interaction
php artisan make:migration create_support_messages_table --no-interaction
```

(Artisan timestamps will differ from the placeholder above — that's fine.)

- [ ] **Step 2: Write the `support_tickets` migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();

            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('closure_note', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'assigned_to']);
            $table->index('user_id');
            $table->index('category');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
```

- [ ] **Step 3: Write the `support_interactions` migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->text('summary');
            $table->timestamp('occurred_at');
            $table->date('follow_up_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'occurred_at']);
            $table->index('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_interactions');
    }
};
```

- [ ] **Step 4: Write the `support_messages` migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->foreignId('interaction_id')->nullable()->constrained('support_interactions')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_phone');
            $table->text('body');
            $table->string('template_key')->nullable();
            $table->string('status')->default('queued');
            $table->string('failed_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sent_by', 'created_at']);
            $table->index('to_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
```

- [ ] **Step 5: Run the migrations**

Run: `php artisan migrate`
Expected: three tables created, no errors.

- [ ] **Step 6: Commit**

```
vendor/bin/pint --dirty --format agent
git add database/migrations/
git commit -m "feat(customer-support): add support_tickets, _interactions, _messages tables"
```

---

## Task 2: Create models, factories, and ticket-number generator

**Files:**
- Create: `app/Models/SupportTicket.php`
- Create: `app/Models/SupportInteraction.php`
- Create: `app/Models/SupportMessage.php`
- Create: `database/factories/SupportTicketFactory.php`
- Create: `database/factories/SupportInteractionFactory.php`
- Create: `database/factories/SupportMessageFactory.php`
- Create: `tests/Unit/SupportTicketModelTest.php`

- [ ] **Step 1: Generate models and factories**

Run:
```
php artisan make:model SupportTicket --factory --no-interaction
php artisan make:model SupportInteraction --factory --no-interaction
php artisan make:model SupportMessage --factory --no-interaction
```

- [ ] **Step 2: Write `app/Models/SupportTicket.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    /** @use HasFactory<\Database\Factories\SupportTicketFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    public const CATEGORIES = [
        'order_issue', 'product_problem', 'vendor_dispute', 'payment_issue',
        'delivery_issue', 'account_help', 'general_inquiry', 'follow_up',
        'check_in', 'onboarding_assistance', 'feedback', 'other',
    ];

    protected $fillable = [
        'ticket_number', 'subject', 'description', 'category', 'priority',
        'status', 'user_id', 'contact_name', 'contact_phone', 'contact_email',
        'order_id', 'report_id', 'assigned_to', 'created_by',
        'closure_note', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (SupportTicket $ticket): void {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    protected static function generateTicketNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "CST-{$date}-";
        $last = self::where('ticket_number', 'like', "{$prefix}%")
            ->orderByDesc('ticket_number')
            ->lockForUpdate()
            ->first();
        $sequence = $last ? ((int) substr($last->ticket_number, -4)) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SupportInteraction::class, 'ticket_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
```

- [ ] **Step 3: Write `app/Models/SupportInteraction.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportInteraction extends Model
{
    /** @use HasFactory<\Database\Factories\SupportInteractionFactory> */
    use HasFactory;

    public const CHANNELS = [
        'phone_call', 'sms', 'whatsapp', 'email', 'in_app_chat', 'in_person', 'other',
    ];

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'ticket_id', 'channel', 'direction', 'summary',
        'occurred_at', 'follow_up_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'follow_up_at' => 'date',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 4: Write `app/Models/SupportMessage.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** @use HasFactory<\Database\Factories\SupportMessageFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const TEMPLATE_KEYS = ['birthday', 'welcome', 'follow_up', 'custom'];

    protected $fillable = [
        'ticket_id', 'interaction_id', 'to_user_id', 'to_phone', 'body',
        'template_key', 'status', 'failed_reason', 'sent_at', 'sent_by',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(SupportInteraction::class, 'interaction_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
```

- [ ] **Step 5: Write `database/factories/SupportTicketFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        return [
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(SupportTicket::CATEGORIES),
            'priority' => SupportTicket::PRIORITY_NORMAL,
            'status' => SupportTicket::STATUS_OPEN,
            'user_id' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => '+233'.fake()->numerify('#########'),
            'contact_email' => fake()->safeEmail(),
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => SupportTicket::STATUS_CLOSED,
            'closure_note' => 'Resolved by phone.',
            'closed_at' => now(),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'contact_name' => $user->name,
            'contact_phone' => $user->phone,
            'contact_email' => $user->email,
        ]);
    }
}
```

- [ ] **Step 6: Write `database/factories/SupportInteractionFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\SupportInteraction;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportInteraction>
 */
class SupportInteractionFactory extends Factory
{
    protected $model = SupportInteraction::class;

    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'channel' => fake()->randomElement(SupportInteraction::CHANNELS),
            'direction' => fake()->randomElement([SupportInteraction::DIRECTION_INBOUND, SupportInteraction::DIRECTION_OUTBOUND]),
            'summary' => fake()->paragraph(),
            'occurred_at' => now(),
            'follow_up_at' => null,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ];
    }

    public function withFollowUp(): self
    {
        return $this->state(fn () => ['follow_up_at' => today()->addDays(2)]);
    }
}
```

- [ ] **Step 7: Write `database/factories/SupportMessageFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    public function definition(): array
    {
        return [
            'to_user_id' => null,
            'to_phone' => '+233'.fake()->numerify('#########'),
            'body' => fake()->sentence(),
            'template_key' => null,
            'status' => SupportMessage::STATUS_QUEUED,
            'sent_by' => User::factory()->create(['role' => 'admin'])->id,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn () => [
            'status' => SupportMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => SupportMessage::STATUS_FAILED,
            'failed_reason' => 'Provider unavailable',
        ]);
    }
}
```

- [ ] **Step 8: Write a unit test for the ticket-number generator**

`tests/Unit/SupportTicketModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_number_is_generated_on_creation(): void
    {
        $ticket = SupportTicket::factory()->create();

        $this->assertMatchesRegularExpression('/^CST-\d{8}-\d{4}$/', $ticket->ticket_number);
    }

    public function test_ticket_numbers_increment_within_a_day(): void
    {
        $first = SupportTicket::factory()->create();
        $second = SupportTicket::factory()->create();

        $firstSeq = (int) substr($first->ticket_number, -4);
        $secondSeq = (int) substr($second->ticket_number, -4);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }
}
```

- [ ] **Step 9: Run the test**

Run: `php artisan test --compact --filter=SupportTicketModelTest`
Expected: 2 passing.

- [ ] **Step 10: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Models/Support*.php database/factories/Support*.php tests/Unit/SupportTicketModelTest.php
git commit -m "feat(customer-support): add SupportTicket, SupportInteraction, SupportMessage models + factories"
```

---

## Task 3: Add SMS templates config

**Files:**
- Create: `config/support_templates.php`

- [ ] **Step 1: Write the config**

```php
<?php

return [
    'birthday' => "Hi {{name}}, happy birthday from all of us at Surprise Moi! 🎉 Wishing you a wonderful year ahead.",
    'welcome' => "Hi {{name}}, welcome to Surprise Moi! Reach us anytime if you need help getting started.",
    'follow_up' => "Hi {{name}}, just following up on our last conversation. Let us know how we can help.",
    'custom' => '',
];
```

- [ ] **Step 2: Verify it loads**

Run: `php artisan tinker --execute="echo config('support_templates.birthday');"`
Expected: prints the birthday string.

- [ ] **Step 3: Commit**

```
git add config/support_templates.php
git commit -m "feat(customer-support): add SMS templates config"
```

---

## Task 4: SupportTicket store endpoint (TDD)

**Files:**
- Create: `app/Http/Requests/Admin/StoreSupportTicketRequest.php`
- Create: `app/Http/Controllers/Admin/SupportTicketController.php`
- Modify: `routes/web.php` (add route inside the `dashboard` middleware group around line 169 near the Reports block)
- Create: `tests/Feature/CustomerSupport/SupportTicketControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\CustomerSupport;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_a_ticket(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/customer-support', [
            'subject' => 'Cannot place order',
            'category' => 'order_issue',
            'priority' => 'normal',
            'contact_name' => 'Ama Mensah',
            'contact_phone' => '+233244111222',
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Cannot place order',
            'category' => 'order_issue',
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status' => 'open',
        ]);
    }

    public function test_subject_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/customer-support', [
            'category' => 'general_inquiry',
            'contact_name' => 'Ama',
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertSessionHasErrors('subject');
    }

    public function test_non_admin_cannot_create_ticket(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post('/dashboard/customer-support', []);

        // EnsureDashboardAccess will redirect non-admins
        $response->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=SupportTicketControllerTest::test_admin_can_create_a_ticket`
Expected: FAIL with route not defined / 404.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/Admin/StoreSupportTicketRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['nullable', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_NORMAL,
                SupportTicket::PRIORITY_HIGH,
            ])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller skeleton with `store`**

`app/Http/Controllers/Admin/SupportTicketController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;

class SupportTicketController extends Controller
{
    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::create([
            ...$request->validated(),
            'priority' => $request->input('priority', SupportTicket::PRIORITY_NORMAL),
            'status' => SupportTicket::STATUS_OPEN,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket created.');
    }
}
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, inside the `Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(function () {` block (the one that contains `reports`, around line 169), add:

```php
    Route::prefix('customer-support')->name('customer-support.')->group(function () {
        Route::post('/', [\App\Http\Controllers\Admin\SupportTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', fn () => '')->name('show'); // placeholder until Task 5
    });
```

The `dashboard.` name prefix is already applied by the parent group's name configuration (verify by reading the surrounding context — if not, write `name('dashboard.customer-support.')`).

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 3 passing (store happy, validation, non-admin redirect).

- [ ] **Step 7: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Admin/StoreSupportTicketRequest.php app/Http/Controllers/Admin/SupportTicketController.php routes/web.php tests/Feature/CustomerSupport/SupportTicketControllerTest.php
git commit -m "feat(customer-support): ticket store endpoint with validation"
```

---

## Task 5: SupportTicket index + show

**Files:**
- Modify: `app/Http/Controllers/Admin/SupportTicketController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CustomerSupport/SupportTicketControllerTest.php`

- [ ] **Step 1: Add failing tests for index and show**

Append to `SupportTicketControllerTest`:

```php
    public function test_admin_index_lists_tickets(): void
    {
        SupportTicket::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/dashboard/customer-support')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/customer-support/index')
                ->has('tickets.data', 3));
    }

    public function test_admin_can_filter_by_status(): void
    {
        SupportTicket::factory()->count(2)->create(['status' => 'open']);
        SupportTicket::factory()->count(1)->closed()->create();

        $this->actingAs($this->admin)
            ->get('/dashboard/customer-support?status=closed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
    }

    public function test_admin_can_view_ticket_detail(): void
    {
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($this->admin)
            ->get("/dashboard/customer-support/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/customer-support/show')
                ->where('ticket.id', $ticket->id));
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 3 new tests fail (route 404 / placeholder returns ''), prior 3 still pass.

- [ ] **Step 3: Implement `index`, `create`, `show` on the controller**

Add to `SupportTicketController`:

```php
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

    public function index(Request $request): Response
    {
        $query = SupportTicket::query()
            ->with([
                'user:id,name,email',
                'assignee:id,name',
                'creator:id,name',
            ])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($priority = $request->string('priority')->toString()) {
            $query->where('priority', $priority);
        }
        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }
        if ($request->boolean('mine')) {
            $query->where('assigned_to', $request->user()->id);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(20)->withQueryString();

        // Follow-ups due today or overdue, scoped to the current admin's open tickets
        $followUps = \App\Models\SupportInteraction::query()
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', '<=', today())
            ->whereHas('ticket', function ($q) use ($request) {
                $q->where('assigned_to', $request->user()->id)
                    ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS]);
            })
            ->with(['ticket:id,ticket_number,subject'])
            ->orderBy('follow_up_at')
            ->limit(20)
            ->get(['id', 'ticket_id', 'follow_up_at', 'summary']);

        return Inertia::render('admin/customer-support/index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority', 'category', 'mine', 'search']),
            'categories' => SupportTicket::CATEGORIES,
            'mine_open_count' => SupportTicket::where('assigned_to', $request->user()->id)
                ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
                ->count(),
            'follow_ups' => $followUps,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/customer-support/create', [
            'admins' => User::query()
                ->whereIn('role', ['admin', 'super_admin'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load([
            'user:id,name,email,phone',
            'assignee:id,name',
            'creator:id,name',
            'closer:id,name',
            'order:id,order_number',
            'report:id,report_number,status',
            'interactions' => fn ($q) => $q->latest('occurred_at')->with('creator:id,name'),
            'messages' => fn ($q) => $q->latest()->with('sender:id,name'),
        ]);

        return Inertia::render('admin/customer-support/show', [
            'ticket' => $ticket,
        ]);
    }
```

- [ ] **Step 4: Update the routes block**

Replace the placeholder routes from Task 4 with:

```php
    Route::prefix('customer-support')->name('customer-support.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SupportTicketController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\SupportTicketController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\SupportTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [\App\Http\Controllers\Admin\SupportTicketController::class, 'show'])->name('show');
    });
```

Route-model binding will auto-resolve `{ticket}` to a `SupportTicket` because the parameter name matches the camelCase model.

- [ ] **Step 5: Run the tests to verify**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 6 passing.

- [ ] **Step 6: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SupportTicketController.php routes/web.php tests/Feature/CustomerSupport/SupportTicketControllerTest.php
git commit -m "feat(customer-support): ticket index, create, show endpoints"
```

---

## Task 6: SupportTicket update endpoint

**Files:**
- Create: `app/Http/Requests/Admin/UpdateSupportTicketRequest.php`
- Modify: `app/Http/Controllers/Admin/SupportTicketController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CustomerSupport/SupportTicketControllerTest.php`

- [ ] **Step 1: Write the failing test**

Append to `SupportTicketControllerTest`:

```php
    public function test_admin_can_update_ticket_header(): void
    {
        $ticket = SupportTicket::factory()->create(['priority' => 'normal']);

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/customer-support/{$ticket->id}", [
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => 'high',
                'contact_name' => $ticket->contact_name,
                'assigned_to' => $this->admin->id,
            ]);

        $response->assertRedirect();
        $this->assertSame('high', $ticket->fresh()->priority);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportTicketControllerTest::test_admin_can_update_ticket_header`
Expected: FAIL.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/Admin/UpdateSupportTicketRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['required', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_NORMAL,
                SupportTicket::PRIORITY_HIGH,
            ])],
            'status' => ['nullable', Rule::in([
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
            ])], // closing/reopening uses dedicated endpoints
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 4: Add `update` to the controller**

```php
use App\Http\Requests\Admin\UpdateSupportTicketRequest;

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->update($request->validated());

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket updated.');
    }
```

- [ ] **Step 5: Add the route**

Inside the `customer-support` group, add:

```php
        Route::patch('/{ticket}', [\App\Http\Controllers\Admin\SupportTicketController::class, 'update'])->name('update');
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 7 passing.

- [ ] **Step 7: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Admin/UpdateSupportTicketRequest.php app/Http/Controllers/Admin/SupportTicketController.php routes/web.php tests/Feature/CustomerSupport/SupportTicketControllerTest.php
git commit -m "feat(customer-support): ticket update endpoint"
```

---

## Task 7: Close + reopen endpoints

**Files:**
- Create: `app/Http/Requests/Admin/CloseSupportTicketRequest.php`
- Modify: `app/Http/Controllers/Admin/SupportTicketController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CustomerSupport/SupportTicketControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `SupportTicketControllerTest`:

```php
    public function test_close_requires_closure_note(): void
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$ticket->id}/close", []);

        $response->assertSessionHasErrors('closure_note');
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_admin_can_close_ticket(): void
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$ticket->id}/close", [
                'closure_note' => 'Refund issued.',
            ]);

        $response->assertRedirect();
        $fresh = $ticket->fresh();
        $this->assertSame('closed', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($this->admin->id, $fresh->closed_by);
        $this->assertSame('Refund issued.', $fresh->closure_note);
    }

    public function test_admin_can_reopen_ticket(): void
    {
        $ticket = SupportTicket::factory()->closed()->create();

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$ticket->id}/reopen");

        $response->assertRedirect();
        $fresh = $ticket->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->closed_by);
        // closure_note preserved as historical record
        $this->assertNotNull($fresh->closure_note);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 3 new failures.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/Admin/CloseSupportTicketRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CloseSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'closure_note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'closure_note.required' => 'A closure note is required to close a ticket.',
        ];
    }
}
```

- [ ] **Step 4: Add `close` and `reopen` to the controller**

```php
use App\Http\Requests\Admin\CloseSupportTicketRequest;

    public function close(CloseSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closure_note' => $request->string('closure_note')->toString(),
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket closed.');
    }

    public function reopen(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update([
            'status' => SupportTicket::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
            // closure_note intentionally preserved
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket reopened.');
    }
```

- [ ] **Step 5: Add the routes**

```php
        Route::post('/{ticket}/close', [\App\Http\Controllers\Admin\SupportTicketController::class, 'close'])->name('close');
        Route::post('/{ticket}/reopen', [\App\Http\Controllers\Admin\SupportTicketController::class, 'reopen'])->name('reopen');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=SupportTicketControllerTest`
Expected: 10 passing.

- [ ] **Step 7: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Admin/CloseSupportTicketRequest.php app/Http/Controllers/Admin/SupportTicketController.php routes/web.php tests/Feature/CustomerSupport/SupportTicketControllerTest.php
git commit -m "feat(customer-support): ticket close + reopen endpoints"
```

---

## Task 8: SupportInteraction store endpoint

**Files:**
- Create: `app/Http/Requests/Admin/StoreSupportInteractionRequest.php`
- Create: `app/Http/Controllers/Admin/SupportInteractionController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/CustomerSupport/SupportInteractionControllerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\CustomerSupport;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportInteractionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SupportTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->ticket = SupportTicket::factory()->create();
    }

    public function test_admin_can_log_an_interaction(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$this->ticket->id}/interactions", [
                'channel' => 'phone_call',
                'direction' => 'outbound',
                'summary' => 'Called about refund. Will call again Friday.',
                'follow_up_at' => today()->addDays(3)->toDateString(),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_interactions', [
            'ticket_id' => $this->ticket->id,
            'channel' => 'phone_call',
            'direction' => 'outbound',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_follow_up_must_be_today_or_future(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$this->ticket->id}/interactions", [
                'channel' => 'phone_call',
                'direction' => 'inbound',
                'summary' => 'x',
                'follow_up_at' => today()->subDay()->toDateString(),
            ]);

        $response->assertSessionHasErrors('follow_up_at');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportInteractionControllerTest`
Expected: FAIL.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/Admin/StoreSupportInteractionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportInteraction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(SupportInteraction::CHANNELS)],
            'direction' => ['required', Rule::in([
                SupportInteraction::DIRECTION_INBOUND,
                SupportInteraction::DIRECTION_OUTBOUND,
            ])],
            'summary' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/SupportInteractionController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportInteractionRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;

class SupportInteractionController extends Controller
{
    public function store(StoreSupportInteractionRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->interactions()->create([
            ...$request->validated(),
            'occurred_at' => $request->date('occurred_at') ?? now(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Interaction logged.');
    }
}
```

- [ ] **Step 5: Add the route**

```php
        Route::post('/{ticket}/interactions', [\App\Http\Controllers\Admin\SupportInteractionController::class, 'store'])->name('interactions.store');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=SupportInteractionControllerTest`
Expected: 2 passing.

- [ ] **Step 7: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Admin/StoreSupportInteractionRequest.php app/Http/Controllers/Admin/SupportInteractionController.php routes/web.php tests/Feature/CustomerSupport/SupportInteractionControllerTest.php
git commit -m "feat(customer-support): log interactions on a ticket"
```

---

## Task 9: SendSupportSmsJob

**Files:**
- Create: `app/Jobs/SendSupportSmsJob.php`
- Create: `tests/Feature/CustomerSupport/SendSupportSmsJobTest.php`

> Before implementing: open `app/Services/KairosAfrikaSmsService.php` and `app/Contracts/Sms/SmsProviderInterface.php` to confirm the exact send-method signature. The code below assumes `send(string $to, string $body): void`. If the contract differs, adapt the call site accordingly.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\CustomerSupport;

use App\Contracts\Sms\SmsProviderInterface;
use App\Jobs\SendSupportSmsJob;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class SendSupportSmsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_sms_and_marks_message_sent(): void
    {
        $message = SupportMessage::factory()->create([
            'to_phone' => '+233244111222',
            'body' => 'Hello',
            'status' => 'queued',
        ]);

        $this->mock(SmsProviderInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with('+233244111222', 'Hello');
        });

        (new SendSupportSmsJob($message->id))->handle(app(SmsProviderInterface::class));

        $fresh = $message->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
    }

    public function test_job_marks_failed_and_rethrows_on_provider_exception(): void
    {
        $message = SupportMessage::factory()->create(['status' => 'queued']);

        $this->mock(SmsProviderInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('provider down'));
        });

        try {
            (new SendSupportSmsJob($message->id))->handle(app(SmsProviderInterface::class));
            $this->fail('Job should rethrow.');
        } catch (RuntimeException $e) {
            $this->assertSame('provider down', $e->getMessage());
        }

        $fresh = $message->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('provider down', $fresh->failed_reason);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SendSupportSmsJobTest`
Expected: FAIL with class not found.

- [ ] **Step 3: Generate the job**

Run: `php artisan make:job SendSupportSmsJob --no-interaction`

- [ ] **Step 4: Write the job body**

`app/Jobs/SendSupportSmsJob.php`:

```php
<?php

namespace App\Jobs;

use App\Contracts\Sms\SmsProviderInterface;
use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SendSupportSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $messageId) {}

    public function handle(SmsProviderInterface $sms): void
    {
        $message = SupportMessage::findOrFail($this->messageId);

        try {
            $sms->send($message->to_phone, $message->body);

            $message->update([
                'status' => SupportMessage::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $message->update([
                'status' => SupportMessage::STATUS_FAILED,
                'failed_reason' => Str::limit($e->getMessage(), 500),
            ]);
            throw $e;
        }
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter=SendSupportSmsJobTest`
Expected: 2 passing.

- [ ] **Step 6: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Jobs/SendSupportSmsJob.php tests/Feature/CustomerSupport/SendSupportSmsJobTest.php
git commit -m "feat(customer-support): SendSupportSmsJob with retries + status tracking"
```

---

## Task 10: SupportMessage — sendForTicket endpoint

**Files:**
- Create: `app/Http/Requests/Admin/SendSupportSmsRequest.php`
- Create: `app/Http/Controllers/Admin/SupportMessageController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/CustomerSupport/SupportMessageControllerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\CustomerSupport;

use App\Jobs\SendSupportSmsJob;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_send_for_ticket_creates_message_and_interaction_and_dispatches_job(): void
    {
        $ticket = SupportTicket::factory()->create([
            'contact_phone' => '+233244111222',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$ticket->id}/sms", [
                'to_phone' => '+233244111222',
                'body' => 'Hi, just following up.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('support_messages', [
            'ticket_id' => $ticket->id,
            'to_phone' => '+233244111222',
            'body' => 'Hi, just following up.',
            'status' => 'queued',
            'sent_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('support_interactions', [
            'ticket_id' => $ticket->id,
            'channel' => 'sms',
            'direction' => 'outbound',
            'created_by' => $this->admin->id,
        ]);

        Queue::assertPushed(SendSupportSmsJob::class);
    }

    public function test_send_to_user_with_no_phone_returns_422(): void
    {
        $userNoPhone = User::factory()->create(['phone' => null]);
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/dashboard/customer-support/{$ticket->id}/sms", [
                'to_user_id' => $userNoPhone->id,
                'body' => 'Hi',
            ]);

        $response->assertStatus(422);
    }

    public function test_body_max_length_enforced(): void
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/dashboard/customer-support/{$ticket->id}/sms", [
                'to_phone' => '+233244111222',
                'body' => str_repeat('a', 481),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    public function test_phone_snapshot_uses_users_current_phone(): void
    {
        $user = User::factory()->create(['phone' => '+233244000111']);
        $ticket = SupportTicket::factory()->forUser($user)->create();

        // user's phone changes after ticket creation
        $user->update(['phone' => '+233244999999']);

        $this->actingAs($this->admin)
            ->post("/dashboard/customer-support/{$ticket->id}/sms", [
                'to_user_id' => $user->id,
                'body' => 'Hi',
            ]);

        $this->assertDatabaseHas('support_messages', [
            'to_user_id' => $user->id,
            'to_phone' => '+233244999999',
        ]);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportMessageControllerTest`
Expected: FAIL.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/Admin/SendSupportSmsRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSupportSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'to_phone' => ['required_without:to_user_id', 'nullable', 'string', 'max:30'],
            'template_key' => ['nullable', Rule::in(SupportMessage::TEMPLATE_KEYS)],
            'body' => ['required', 'string', 'max:480'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/SupportMessageController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSupportSmsRequest;
use App\Jobs\SendSupportSmsJob;
use App\Models\SupportInteraction;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportMessageController extends Controller
{
    public function sendForTicket(SendSupportSmsRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $phone = $this->resolveRecipientPhone($request);
        $body = $this->renderBody($request);

        DB::transaction(function () use ($request, $ticket, $phone, $body) {
            $interaction = SupportInteraction::create([
                'ticket_id' => $ticket->id,
                'channel' => 'sms',
                'direction' => SupportInteraction::DIRECTION_OUTBOUND,
                'summary' => Str::limit($body, 200),
                'occurred_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            $message = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'interaction_id' => $interaction->id,
                'to_user_id' => $request->integer('to_user_id') ?: null,
                'to_phone' => $phone,
                'body' => $body,
                'template_key' => $request->input('template_key'),
                'status' => SupportMessage::STATUS_QUEUED,
                'sent_by' => $request->user()->id,
            ]);

            DB::afterCommit(fn () => SendSupportSmsJob::dispatch($message->id));
        });

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'SMS queued.');
    }

    /**
     * Resolve the actual phone number to dispatch to.
     * Snapshots into support_messages.to_phone so the log is truthful even
     * if the user later changes their phone.
     */
    protected function resolveRecipientPhone(SendSupportSmsRequest $request): string
    {
        if ($userId = $request->integer('to_user_id')) {
            $user = User::findOrFail($userId);
            if (empty($user->phone)) {
                throw ValidationException::withMessages([
                    'to_user_id' => 'This contact has no phone number on file.',
                ]);
            }
            return $user->phone;
        }

        return (string) $request->string('to_phone');
    }

    /**
     * Substitute {{name}} in the body if a template is in play.
     * The body is the post-edit version the CSR sent, so substitution is
     * a safety net rather than the primary template render.
     */
    protected function renderBody(SendSupportSmsRequest $request): string
    {
        $body = (string) $request->string('body');

        if (! str_contains($body, '{{name}}')) {
            return $body;
        }

        $name = 'there';
        if ($userId = $request->integer('to_user_id')) {
            $name = User::find($userId)?->name ?? $name;
        }

        return str_replace('{{name}}', $name, $body);
    }
}
```

- [ ] **Step 5: Add the route**

```php
        Route::post('/{ticket}/sms', [\App\Http\Controllers\Admin\SupportMessageController::class, 'sendForTicket'])->name('sms.ticket');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=SupportMessageControllerTest`
Expected: 4 passing.

- [ ] **Step 7: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Admin/SendSupportSmsRequest.php app/Http/Controllers/Admin/SupportMessageController.php routes/web.php tests/Feature/CustomerSupport/SupportMessageControllerTest.php
git commit -m "feat(customer-support): send SMS in ticket context (queued, with auto interaction)"
```

---

## Task 11: Standalone SMS endpoint + messaging page + log

**Files:**
- Modify: `app/Http/Controllers/Admin/SupportMessageController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CustomerSupport/SupportMessageControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `SupportMessageControllerTest`:

```php
    public function test_standalone_send_creates_message_only(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/dashboard/customer-support/messaging/sms', [
                'to_phone' => '+233244000000',
                'template_key' => 'birthday',
                'body' => 'Happy birthday!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_messages', [
            'ticket_id' => null,
            'interaction_id' => null,
            'to_phone' => '+233244000000',
            'template_key' => 'birthday',
            'status' => 'queued',
        ]);
        $this->assertDatabaseCount('support_interactions', 0);
    }

    public function test_messaging_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard/customer-support/messaging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/customer-support/index'));
    }

    public function test_log_returns_paginated_messages(): void
    {
        \App\Models\SupportMessage::factory()->count(5)->sent()->create(['sent_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/customer-support/messaging/log');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportMessageControllerTest`
Expected: 3 new failures.

- [ ] **Step 3: Add `storeStandalone`, `page`, `log` methods**

Add to `SupportMessageController`:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

    public function storeStandalone(SendSupportSmsRequest $request): RedirectResponse
    {
        $phone = $this->resolveRecipientPhone($request);
        $body = $this->renderBody($request);

        DB::transaction(function () use ($request, $phone, $body) {
            $message = SupportMessage::create([
                'ticket_id' => null,
                'interaction_id' => null,
                'to_user_id' => $request->integer('to_user_id') ?: null,
                'to_phone' => $phone,
                'body' => $body,
                'template_key' => $request->input('template_key'),
                'status' => SupportMessage::STATUS_QUEUED,
                'sent_by' => $request->user()->id,
            ]);

            DB::afterCommit(fn () => SendSupportSmsJob::dispatch($message->id));
        });

        return redirect()
            ->route('dashboard.customer-support.messaging')
            ->with('success', 'SMS queued.');
    }

    public function page(): Response
    {
        // Renders the same shell page as `index`, with the Messaging tab
        // selected by URL hash on the client side.
        return Inertia::render('admin/customer-support/index', [
            'tickets' => null,
            'messaging_active' => true,
        ]);
    }

    public function log(Request $request): JsonResponse
    {
        $query = SupportMessage::query()
            ->with(['recipient:id,name', 'sender:id,name', 'ticket:id,ticket_number'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhere('to_phone', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }
```

- [ ] **Step 4: Add the routes**

```php
        Route::get('/messaging', [\App\Http\Controllers\Admin\SupportMessageController::class, 'page'])->name('messaging');
        Route::post('/messaging/sms', [\App\Http\Controllers\Admin\SupportMessageController::class, 'storeStandalone'])->name('messaging.sms');
        Route::get('/messaging/log', [\App\Http\Controllers\Admin\SupportMessageController::class, 'log'])->name('messaging.log');
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter=SupportMessageControllerTest`
Expected: 7 passing.

- [ ] **Step 6: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SupportMessageController.php routes/web.php tests/Feature/CustomerSupport/SupportMessageControllerTest.php
git commit -m "feat(customer-support): standalone SMS send + messaging page + outbound log"
```

---

## Task 12: SupportContact — search + birthdays endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/SupportContactController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/CustomerSupport/SupportContactControllerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\CustomerSupport;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportContactControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_search_matches_by_name_email_and_phone(): void
    {
        User::factory()->create(['name' => 'Ama Mensah', 'role' => 'customer']);
        User::factory()->create(['name' => 'Kojo', 'email' => 'kojo.searchme@example.com', 'role' => 'vendor']);
        User::factory()->create(['name' => 'Other', 'phone' => '+233244888999', 'role' => 'customer']);

        $byName = $this->actingAs($this->admin)->getJson('/dashboard/customer-support/contacts/search?q=Ama');
        $byEmail = $this->actingAs($this->admin)->getJson('/dashboard/customer-support/contacts/search?q=searchme');
        $byPhone = $this->actingAs($this->admin)->getJson('/dashboard/customer-support/contacts/search?q=888999');

        $this->assertCount(1, $byName->json());
        $this->assertCount(1, $byEmail->json());
        $this->assertCount(1, $byPhone->json());
    }

    public function test_birthdays_lists_users_with_birthday_in_window(): void
    {
        User::factory()->create([
            'date_of_birth' => now()->subYears(25)->setMonth(now()->month)->setDay(now()->day),
            'role' => 'customer',
        ]);
        User::factory()->create([
            'date_of_birth' => now()->subYears(30)->setMonth(now()->addMonths(2)->month)->setDay(15),
            'role' => 'vendor',
        ]);
        User::factory()->create([
            'date_of_birth' => null,
            'role' => 'customer',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/dashboard/customer-support/birthdays');

        $response->assertOk();
        $payload = $response->json();
        $this->assertCount(1, $payload['today']);
        $this->assertSame([], $payload['this_week']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=SupportContactControllerTest`
Expected: FAIL.

- [ ] **Step 3: Write the controller**

`app/Http/Controllers/Admin/SupportContactController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportContactController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();

        if ($q === '') {
            return response()->json([]);
        }

        $matches = User::query()
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'role']);

        return response()->json($matches);
    }

    public function birthdays(): JsonResponse
    {
        $today = now()->startOfDay();
        $weekEnd = now()->copy()->addDays(7)->endOfDay();

        $users = User::query()
            ->whereNotNull('date_of_birth')
            ->whereIn('role', ['customer', 'vendor', 'influencer', 'field_agent', 'marketer'])
            ->get(['id', 'name', 'role', 'phone', 'date_of_birth']);

        $todayList = [];
        $weekList = [];

        foreach ($users as $user) {
            $dob = $user->date_of_birth;
            $thisYearBirthday = $today->copy()->setMonth($dob->month)->setDay($dob->day);

            if ($thisYearBirthday->isSameDay($today)) {
                $todayList[] = $this->formatBirthdayUser($user, $thisYearBirthday);
            } elseif ($thisYearBirthday->between($today->copy()->addDay(), $weekEnd)) {
                $weekList[] = $this->formatBirthdayUser($user, $thisYearBirthday);
            }
        }

        return response()->json([
            'today' => $todayList,
            'this_week' => $weekList,
        ]);
    }

    /**
     * @return array{id:int,name:string,role:string,phone:?string,age_turning:int,birthday_date:string}
     */
    protected function formatBirthdayUser(User $user, \Illuminate\Support\Carbon $thisYearBirthday): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'phone' => $user->phone,
            'age_turning' => $thisYearBirthday->year - $user->date_of_birth->year,
            'birthday_date' => $thisYearBirthday->toDateString(),
        ];
    }
}
```

- [ ] **Step 4: Add the routes**

```php
        Route::get('/contacts/search', [\App\Http\Controllers\Admin\SupportContactController::class, 'search'])->name('contacts.search');
        Route::get('/birthdays', [\App\Http\Controllers\Admin\SupportContactController::class, 'birthdays'])->name('birthdays');
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter=SupportContactControllerTest`
Expected: 2 passing.

- [ ] **Step 6: Commit**

```
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SupportContactController.php routes/web.php tests/Feature/CustomerSupport/SupportContactControllerTest.php
git commit -m "feat(customer-support): contact search + birthdays endpoints"
```

---

## Task 13: Regenerate Wayfinder + add sidebar entry

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`
- Auto-generated: `resources/js/actions/...`, `resources/js/routes/...`

- [ ] **Step 1: Regenerate Wayfinder action types**

Run: `php artisan wayfinder:generate`
Expected: new files appear under `resources/js/actions/App/Http/Controllers/Admin/SupportTicketController.ts` and similar.

- [ ] **Step 2: Add sidebar entry**

Open `resources/js/components/app-sidebar.tsx`. In the existing `'Support'` group (around line 169) replace:

```tsx
            {
                title: 'Support',
                icon: AlertTriangle,
                items: [
                    {
                        title: 'Reports & Conflicts',
                        href: '/dashboard/reports',
                        icon: AlertTriangle,
                    },
                ],
            },
```

with:

```tsx
            {
                title: 'Support',
                icon: AlertTriangle,
                items: [
                    {
                        title: 'Customer Support',
                        href: '/dashboard/customer-support',
                        icon: Headset,
                    },
                    {
                        title: 'Reports & Conflicts',
                        href: '/dashboard/reports',
                        icon: AlertTriangle,
                    },
                ],
            },
```

Add `Headset` to the lucide-react import block at the top of the file.

- [ ] **Step 3: Verify build**

Run: `pnpm run build`
Expected: build succeeds, no TS errors.

- [ ] **Step 4: Commit**

```
git add resources/js/components/app-sidebar.tsx resources/js/actions resources/js/routes
git commit -m "feat(customer-support): wire sidebar entry and regen Wayfinder actions"
```

---

## Task 14: Reusable React components — badges + ContactPicker

**Files:**
- Create: `resources/js/components/admin/customer-support/TicketStatusBadge.tsx`
- Create: `resources/js/components/admin/customer-support/PriorityBadge.tsx`
- Create: `resources/js/components/admin/customer-support/ContactPicker.tsx`

- [ ] **Step 1: Write `TicketStatusBadge.tsx`**

```tsx
import { Badge } from '@/components/ui/badge';

const styles: Record<string, string> = {
    open: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-amber-100 text-amber-800',
    closed: 'bg-emerald-100 text-emerald-800',
};

const labels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In Progress',
    closed: 'Closed',
};

export function TicketStatusBadge({ status }: { status: string }) {
    return <Badge className={styles[status] ?? ''}>{labels[status] ?? status}</Badge>;
}
```

- [ ] **Step 2: Write `PriorityBadge.tsx`**

```tsx
import { Badge } from '@/components/ui/badge';

const styles: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700',
    normal: 'bg-sky-100 text-sky-800',
    high: 'bg-rose-100 text-rose-800',
};

export function PriorityBadge({ priority }: { priority: string }) {
    return <Badge className={styles[priority] ?? ''}>{priority}</Badge>;
}
```

- [ ] **Step 3: Write `ContactPicker.tsx`**

```tsx
import { Input } from '@/components/ui/input';
import { useEffect, useState } from 'react';

export type ContactSuggestion = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: string;
};

type Props = {
    onSelect: (contact: ContactSuggestion) => void;
    placeholder?: string;
};

export function ContactPicker({ onSelect, placeholder = 'Search by name, email, or phone…' }: Props) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState<ContactSuggestion[]>([]);

    useEffect(() => {
        if (q.length < 2) {
            setResults([]);
            return;
        }
        const id = setTimeout(async () => {
            const res = await fetch(`/dashboard/customer-support/contacts/search?q=${encodeURIComponent(q)}`);
            if (res.ok) setResults(await res.json());
        }, 200);
        return () => clearTimeout(id);
    }, [q]);

    return (
        <div className="relative">
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} />
            {results.length > 0 && (
                <ul className="absolute left-0 right-0 top-full z-10 mt-1 max-h-72 overflow-auto rounded border bg-white shadow">
                    {results.map((c) => (
                        <li
                            key={c.id}
                            className="cursor-pointer px-3 py-2 text-sm hover:bg-slate-50"
                            onClick={() => {
                                onSelect(c);
                                setQ('');
                                setResults([]);
                            }}
                        >
                            <div className="font-medium">{c.name}</div>
                            <div className="text-xs text-slate-500">
                                {c.role} · {c.phone ?? 'no phone'} · {c.email ?? '—'}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Verify build**

Run: `pnpm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```
git add resources/js/components/admin/customer-support/
git commit -m "feat(customer-support): add badge + contact picker components"
```

---

## Task 15: SmsComposer + InteractionForm + InteractionTimeline

**Files:**
- Create: `resources/js/components/admin/customer-support/SmsComposer.tsx`
- Create: `resources/js/components/admin/customer-support/InteractionForm.tsx`
- Create: `resources/js/components/admin/customer-support/InteractionTimeline.tsx`

- [ ] **Step 1: Write `SmsComposer.tsx`**

```tsx
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ContactPicker, ContactSuggestion } from './ContactPicker';

type Props = {
    endpoint: string;             // e.g. /dashboard/customer-support/messaging/sms
    initialBody?: string;
    initialTemplate?: string;
    initialRecipient?: ContactSuggestion;
    onSent?: () => void;
};

const TEMPLATES: Record<string, string> = {
    birthday: 'Hi {{name}}, happy birthday from all of us at Surprise Moi! 🎉',
    welcome: 'Hi {{name}}, welcome to Surprise Moi!',
    follow_up: 'Hi {{name}}, just following up on our last conversation.',
    custom: '',
};

export function SmsComposer({ endpoint, initialBody = '', initialTemplate = '', initialRecipient, onSent }: Props) {
    const [recipient, setRecipient] = useState<ContactSuggestion | null>(initialRecipient ?? null);
    const [phone, setPhone] = useState(initialRecipient?.phone ?? '');
    const [template, setTemplate] = useState(initialTemplate);
    const [body, setBody] = useState(initialBody);
    const [submitting, setSubmitting] = useState(false);

    const segments = Math.max(1, Math.ceil(body.length / 160));

    function applyTemplate(key: string) {
        setTemplate(key);
        if (TEMPLATES[key] !== undefined) setBody(TEMPLATES[key]);
    }

    function send() {
        setSubmitting(true);
        router.post(
            endpoint,
            {
                to_user_id: recipient?.id ?? null,
                to_phone: recipient?.phone ?? phone ?? null,
                template_key: template || null,
                body,
            },
            {
                onSuccess: () => {
                    setBody('');
                    setTemplate('');
                    setRecipient(null);
                    setPhone('');
                    onSent?.();
                },
                onFinish: () => setSubmitting(false),
            }
        );
    }

    const noPhone = !recipient?.phone && !phone;

    return (
        <div className="space-y-4 rounded border p-4">
            <div>
                <Label>Recipient</Label>
                {recipient ? (
                    <div className="flex items-center justify-between rounded border bg-slate-50 px-3 py-2 text-sm">
                        <div>
                            <div className="font-medium">{recipient.name}</div>
                            <div className="text-xs text-slate-500">{recipient.phone ?? 'no phone'}</div>
                        </div>
                        <Button variant="ghost" size="sm" onClick={() => setRecipient(null)}>Change</Button>
                    </div>
                ) : (
                    <>
                        <ContactPicker onSelect={(c) => { setRecipient(c); setPhone(c.phone ?? ''); }} />
                        <div className="mt-2 text-xs text-slate-500">Or type a number directly:</div>
                        <input
                            className="mt-1 w-full rounded border px-3 py-2 text-sm"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="+233…"
                        />
                    </>
                )}
            </div>

            <div>
                <Label>Template</Label>
                <Select value={template} onValueChange={applyTemplate}>
                    <SelectTrigger><SelectValue placeholder="(none)" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">(none)</SelectItem>
                        <SelectItem value="birthday">Birthday</SelectItem>
                        <SelectItem value="welcome">Welcome</SelectItem>
                        <SelectItem value="follow_up">Follow up</SelectItem>
                        <SelectItem value="custom">Custom (blank)</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div>
                <Label>Message</Label>
                <Textarea value={body} onChange={(e) => setBody(e.target.value)} rows={4} maxLength={480} />
                <div className="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{body.length}/480</span>
                    <span>{segments} segment{segments > 1 ? 's' : ''}</span>
                </div>
            </div>

            <Button onClick={send} disabled={submitting || noPhone || body.length === 0}>
                {submitting ? 'Sending…' : 'Send SMS'}
            </Button>
            {noPhone && <p className="text-xs text-rose-600">Pick a contact with a phone number, or type one above.</p>}
        </div>
    );
}
```

- [ ] **Step 2: Write `InteractionForm.tsx`**

```tsx
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type Props = { ticketId: number };

const CHANNELS = ['phone_call', 'sms', 'whatsapp', 'email', 'in_app_chat', 'in_person', 'other'];

export function InteractionForm({ ticketId }: Props) {
    const [channel, setChannel] = useState('phone_call');
    const [direction, setDirection] = useState<'inbound' | 'outbound'>('outbound');
    const [summary, setSummary] = useState('');
    const [followUp, setFollowUp] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        setSubmitting(true);
        router.post(
            `/dashboard/customer-support/${ticketId}/interactions`,
            { channel, direction, summary, follow_up_at: followUp || null },
            { onFinish: () => { setSubmitting(false); setSummary(''); setFollowUp(''); } },
        );
    }

    return (
        <div className="space-y-3 rounded border p-4">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <Label>Channel</Label>
                    <Select value={channel} onValueChange={setChannel}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {CHANNELS.map((c) => <SelectItem key={c} value={c}>{c.replace('_', ' ')}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Direction</Label>
                    <Select value={direction} onValueChange={(v) => setDirection(v as 'inbound' | 'outbound')}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="inbound">Inbound (they contacted us)</SelectItem>
                            <SelectItem value="outbound">Outbound (we contacted them)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div>
                <Label>What happened?</Label>
                <Textarea value={summary} onChange={(e) => setSummary(e.target.value)} rows={3} />
            </div>

            <div>
                <Label>Follow-up date (optional)</Label>
                <Input type="date" value={followUp} onChange={(e) => setFollowUp(e.target.value)} />
            </div>

            <Button onClick={submit} disabled={submitting || summary.length === 0}>
                {submitting ? 'Saving…' : 'Log Interaction'}
            </Button>
        </div>
    );
}
```

- [ ] **Step 3: Write `InteractionTimeline.tsx`**

```tsx
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';

type Interaction = {
    id: number;
    channel: string;
    direction: 'inbound' | 'outbound';
    summary: string;
    occurred_at: string;
    follow_up_at: string | null;
    creator: { id: number; name: string };
};

export function InteractionTimeline({ interactions }: { interactions: Interaction[] }) {
    if (interactions.length === 0) {
        return <p className="rounded border p-4 text-sm text-slate-500">No interactions logged yet.</p>;
    }

    return (
        <ul className="space-y-3">
            {interactions.map((i) => (
                <li key={i.id} className="rounded border p-3">
                    <div className="flex items-center gap-2 text-xs text-slate-500">
                        {i.direction === 'inbound' ? <ArrowDownLeft size={14} /> : <ArrowUpRight size={14} />}
                        <span className="font-medium uppercase tracking-wide">{i.channel.replace('_', ' ')}</span>
                        <span>·</span>
                        <span>{new Date(i.occurred_at).toLocaleString()}</span>
                        <span>·</span>
                        <span>{i.creator.name}</span>
                    </div>
                    <p className="mt-2 whitespace-pre-line text-sm">{i.summary}</p>
                    {i.follow_up_at && (
                        <p className="mt-2 text-xs text-amber-700">Follow-up: {i.follow_up_at}</p>
                    )}
                </li>
            ))}
        </ul>
    );
}
```

- [ ] **Step 4: Verify build**

Run: `pnpm run build`

- [ ] **Step 5: Commit**

```
git add resources/js/components/admin/customer-support/
git commit -m "feat(customer-support): SmsComposer, InteractionForm, InteractionTimeline"
```

---

## Task 16: Create page

**Files:**
- Create: `resources/js/Pages/admin/customer-support/create.tsx`

- [ ] **Step 1: Write `create.tsx`**

```tsx
import AppLayout from '@/layouts/app/app-sidebar-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ContactPicker, ContactSuggestion } from '@/components/admin/customer-support/ContactPicker';

type Props = {
    admins: Array<{ id: number; name: string }>;
    categories: string[];
};

export default function Create({ admins, categories }: Props) {
    const [contact, setContact] = useState<ContactSuggestion | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        subject: '',
        category: 'general_inquiry',
        priority: 'normal',
        description: '',
        user_id: null as number | null,
        contact_name: '',
        contact_phone: '',
        contact_email: '',
        order_id: null as number | null,
        report_id: null as number | null,
        assigned_to: admins[0]?.id ?? null,
    });

    function pickContact(c: ContactSuggestion) {
        setContact(c);
        setData((d) => ({
            ...d,
            user_id: c.id,
            contact_name: c.name,
            contact_phone: c.phone ?? '',
            contact_email: c.email ?? '',
        }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/dashboard/customer-support');
    }

    return (
        <AppLayout>
            <Head title="New Customer Support Ticket" />
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-4 p-6">
                <h1 className="text-xl font-semibold">New Ticket</h1>

                <div>
                    <Label>Contact</Label>
                    {contact ? (
                        <div className="flex items-center justify-between rounded border bg-slate-50 px-3 py-2 text-sm">
                            <div>
                                <div className="font-medium">{contact.name}</div>
                                <div className="text-xs text-slate-500">{contact.role} · {contact.phone ?? 'no phone'}</div>
                            </div>
                            <Button type="button" variant="ghost" size="sm" onClick={() => { setContact(null); setData('user_id', null); }}>Change</Button>
                        </div>
                    ) : (
                        <ContactPicker onSelect={pickContact} placeholder="Search registered users…" />
                    )}
                    <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <Label>Name</Label>
                            <Input value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} />
                        </div>
                        <div>
                            <Label>Phone</Label>
                            <Input value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
                        </div>
                    </div>
                    {errors.contact_name && <p className="text-xs text-rose-600">{errors.contact_name}</p>}
                </div>

                <div>
                    <Label>Subject</Label>
                    <Input value={data.subject} onChange={(e) => setData('subject', e.target.value)} />
                    {errors.subject && <p className="text-xs text-rose-600">{errors.subject}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Category</Label>
                        <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {categories.map((c) => <SelectItem key={c} value={c}>{c.replace('_', ' ')}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Priority</Label>
                        <Select value={data.priority} onValueChange={(v) => setData('priority', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="normal">Normal</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div>
                    <Label>Assign to</Label>
                    <Select
                        value={data.assigned_to ? String(data.assigned_to) : ''}
                        onValueChange={(v) => setData('assigned_to', Number(v))}
                    >
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {admins.map((a) => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label>Notes (optional)</Label>
                    <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={4} />
                </div>

                <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create Ticket'}</Button>
            </form>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify build**

Run: `pnpm run build`

- [ ] **Step 3: Commit**

```
git add resources/js/Pages/admin/customer-support/create.tsx
git commit -m "feat(customer-support): create ticket page"
```

---

## Task 17: Show page (ticket detail)

**Files:**
- Create: `resources/js/Pages/admin/customer-support/show.tsx`

- [ ] **Step 1: Write `show.tsx`**

```tsx
import AppLayout from '@/layouts/app/app-sidebar-layout';
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { InteractionForm } from '@/components/admin/customer-support/InteractionForm';
import { InteractionTimeline } from '@/components/admin/customer-support/InteractionTimeline';
import { PriorityBadge } from '@/components/admin/customer-support/PriorityBadge';
import { SmsComposer } from '@/components/admin/customer-support/SmsComposer';
import { TicketStatusBadge } from '@/components/admin/customer-support/TicketStatusBadge';

type Ticket = {
    id: number;
    ticket_number: string;
    subject: string;
    category: string;
    priority: string;
    status: string;
    contact_name: string;
    contact_phone: string | null;
    contact_email: string | null;
    closure_note: string | null;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    assignee: { id: number; name: string } | null;
    creator: { id: number; name: string };
    order: { id: number; order_number: string } | null;
    report: { id: number; report_number: string; status: string } | null;
    interactions: Array<{
        id: number; channel: string; direction: 'inbound' | 'outbound';
        summary: string; occurred_at: string; follow_up_at: string | null;
        creator: { id: number; name: string };
    }>;
};

export default function Show({ ticket }: { ticket: Ticket }) {
    const [showSms, setShowSms] = useState(false);
    const [showInteraction, setShowInteraction] = useState(false);
    const [showClose, setShowClose] = useState(false);
    const [closureNote, setClosureNote] = useState('');

    function close() {
        router.post(`/dashboard/customer-support/${ticket.id}/close`, { closure_note: closureNote });
    }

    function reopen() {
        router.post(`/dashboard/customer-support/${ticket.id}/reopen`);
    }

    return (
        <AppLayout>
            <Head title={ticket.ticket_number} />
            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <header className="flex items-start justify-between">
                    <div>
                        <p className="text-xs text-slate-500">{ticket.ticket_number}</p>
                        <h1 className="text-xl font-semibold">{ticket.subject}</h1>
                        <div className="mt-2 flex items-center gap-2">
                            <TicketStatusBadge status={ticket.status} />
                            <PriorityBadge priority={ticket.priority} />
                            <span className="text-xs text-slate-500">{ticket.category.replace('_', ' ')}</span>
                            {ticket.assignee && <span className="text-xs text-slate-500">· {ticket.assignee.name}</span>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setShowInteraction((s) => !s)}>Add Interaction</Button>
                        <Button variant="outline" onClick={() => setShowSms((s) => !s)}>Send SMS</Button>
                        {ticket.status !== 'closed' ? (
                            <Button variant="destructive" onClick={() => setShowClose((s) => !s)}>Close</Button>
                        ) : (
                            <Button onClick={reopen}>Reopen</Button>
                        )}
                    </div>
                </header>

                <section className="rounded border p-4 text-sm">
                    <h2 className="mb-2 font-medium">Contact</h2>
                    <p>{ticket.contact_name}</p>
                    <p className="text-slate-500">{ticket.contact_phone ?? '—'} · {ticket.contact_email ?? '—'}</p>
                    {ticket.order && <p className="mt-2 text-xs">Order: {ticket.order.order_number}</p>}
                    {ticket.report && <p className="text-xs">Report: {ticket.report.report_number} ({ticket.report.status})</p>}
                </section>

                {showSms && (
                    <SmsComposer
                        endpoint={`/dashboard/customer-support/${ticket.id}/sms`}
                        initialRecipient={
                            ticket.user
                                ? { id: ticket.user.id, name: ticket.user.name, email: ticket.user.email, phone: ticket.user.phone, role: '' }
                                : undefined
                        }
                        onSent={() => setShowSms(false)}
                    />
                )}

                {showInteraction && <InteractionForm ticketId={ticket.id} />}

                {showClose && (
                    <div className="space-y-2 rounded border border-rose-200 bg-rose-50 p-4">
                        <label className="text-sm font-medium">Closure note</label>
                        <textarea
                            className="w-full rounded border px-3 py-2 text-sm"
                            value={closureNote}
                            onChange={(e) => setClosureNote(e.target.value)}
                            rows={3}
                        />
                        <Button variant="destructive" disabled={closureNote.length === 0} onClick={close}>Confirm Close</Button>
                    </div>
                )}

                <section>
                    <h2 className="mb-2 text-sm font-medium">Interactions</h2>
                    <InteractionTimeline interactions={ticket.interactions} />
                </section>

                {ticket.closure_note && (
                    <section className="rounded border bg-slate-50 p-4 text-sm">
                        <h2 className="mb-1 font-medium">Closure note</h2>
                        <p>{ticket.closure_note}</p>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify build**

Run: `pnpm run build`

- [ ] **Step 3: Commit**

```
git add resources/js/Pages/admin/customer-support/show.tsx
git commit -m "feat(customer-support): ticket detail page"
```

---

## Task 18: Index page — Tickets tab + tab shell

**Files:**
- Create: `resources/js/Pages/admin/customer-support/index.tsx`

- [ ] **Step 1: Write `index.tsx`**

```tsx
import AppLayout from '@/layouts/app/app-sidebar-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { PriorityBadge } from '@/components/admin/customer-support/PriorityBadge';
import { TicketStatusBadge } from '@/components/admin/customer-support/TicketStatusBadge';
import { Messaging } from './messaging-tab';
import { Birthdays } from './birthdays-tab';

type TicketRow = {
    id: number;
    ticket_number: string;
    subject: string;
    contact_name: string;
    category: string;
    priority: string;
    status: string;
    assignee: { id: number; name: string } | null;
    updated_at: string;
};

type FollowUp = {
    id: number;
    ticket_id: number;
    follow_up_at: string;
    summary: string;
    ticket: { id: number; ticket_number: string; subject: string };
};

type Props = {
    tickets: { data: TicketRow[]; links: { url: string | null; label: string; active: boolean }[] } | null;
    filters: { status?: string; priority?: string; category?: string; mine?: boolean; search?: string };
    categories: string[];
    mine_open_count: number;
    follow_ups?: FollowUp[];
    messaging_active?: boolean;
};

export default function Index({ tickets, filters, categories, mine_open_count, follow_ups = [], messaging_active }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilter(key: string, value: string | null) {
        router.get('/dashboard/customer-support', { ...filters, [key]: value || undefined }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Customer Support" />
            <div className="mx-auto max-w-6xl p-6">
                <header className="mb-4 flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Customer Support</h1>
                    <Link href="/dashboard/customer-support/create">
                        <Button>New Ticket</Button>
                    </Link>
                </header>

                <Tabs defaultValue={messaging_active ? 'messaging' : 'tickets'}>
                    <TabsList>
                        <TabsTrigger value="tickets">Tickets ({mine_open_count} mine)</TabsTrigger>
                        <TabsTrigger value="messaging">Messaging</TabsTrigger>
                        <TabsTrigger value="birthdays">Birthdays</TabsTrigger>
                    </TabsList>

                    <TabsContent value="tickets" className="grid grid-cols-1 gap-4 pt-4 lg:grid-cols-[1fr_280px]">
                        <div className="space-y-3">
                        <div className="flex flex-wrap gap-2">
                            <Input
                                placeholder="Search ticket #, subject, contact…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onBlur={() => applyFilter('search', search)}
                                className="max-w-sm"
                            />
                            <Button variant={filters.status === 'open' ? 'default' : 'outline'} size="sm" onClick={() => applyFilter('status', 'open')}>Open</Button>
                            <Button variant={filters.status === 'in_progress' ? 'default' : 'outline'} size="sm" onClick={() => applyFilter('status', 'in_progress')}>In Progress</Button>
                            <Button variant={filters.status === 'closed' ? 'default' : 'outline'} size="sm" onClick={() => applyFilter('status', 'closed')}>Closed</Button>
                            <Button variant={filters.mine ? 'default' : 'outline'} size="sm" onClick={() => applyFilter('mine', filters.mine ? '' : '1')}>My tickets</Button>
                        </div>

                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="py-2">Ticket</th>
                                    <th>Subject</th>
                                    <th>Contact</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assignee</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tickets?.data.map((t) => (
                                    <tr key={t.id} className="border-t">
                                        <td className="py-2">
                                            <Link href={`/dashboard/customer-support/${t.id}`} className="text-blue-600 hover:underline">{t.ticket_number}</Link>
                                        </td>
                                        <td>{t.subject}</td>
                                        <td>{t.contact_name}</td>
                                        <td>{t.category.replace('_', ' ')}</td>
                                        <td><PriorityBadge priority={t.priority} /></td>
                                        <td><TicketStatusBadge status={t.status} /></td>
                                        <td>{t.assignee?.name ?? '—'}</td>
                                    </tr>
                                ))}
                                {tickets?.data.length === 0 && (
                                    <tr><td colSpan={7} className="py-6 text-center text-slate-500">No tickets match.</td></tr>
                                )}
                            </tbody>
                        </table>
                        </div>

                        <aside className="rounded border bg-amber-50 p-3 text-sm">
                            <h3 className="mb-2 font-medium">Follow-ups due / overdue</h3>
                            {follow_ups.length === 0 ? (
                                <p className="text-xs text-slate-500">Nothing overdue. Nice.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {follow_ups.map((f) => (
                                        <li key={f.id}>
                                            <Link
                                                href={`/dashboard/customer-support/${f.ticket.id}`}
                                                className="block rounded border bg-white p-2 hover:bg-slate-50"
                                            >
                                                <div className="text-xs font-medium text-slate-700">{f.ticket.ticket_number}</div>
                                                <div className="text-xs text-slate-500">{f.ticket.subject}</div>
                                                <div className="mt-1 text-xs text-amber-700">Due {f.follow_up_at}</div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </aside>
                    </TabsContent>

                    <TabsContent value="messaging" className="pt-4"><Messaging /></TabsContent>
                    <TabsContent value="birthdays" className="pt-4"><Birthdays /></TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Commit (page won't build yet — Messaging/Birthdays imports come next)**

Skip build for now; do build at the end of Task 20.

```
git add resources/js/Pages/admin/customer-support/index.tsx
git commit -m "feat(customer-support): index page shell with tickets tab"
```

---

## Task 19: Index page — Messaging tab

**Files:**
- Create: `resources/js/Pages/admin/customer-support/messaging-tab.tsx`

- [ ] **Step 1: Write `messaging-tab.tsx`**

```tsx
import { useEffect, useState } from 'react';
import { SmsComposer } from '@/components/admin/customer-support/SmsComposer';
import { Badge } from '@/components/ui/badge';

type LogRow = {
    id: number;
    to_phone: string;
    body: string;
    status: 'queued' | 'sent' | 'failed';
    created_at: string;
    sender: { id: number; name: string } | null;
    recipient: { id: number; name: string } | null;
};

const statusColors: Record<string, string> = {
    queued: 'bg-amber-100 text-amber-800',
    sent: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};

export function Messaging() {
    const [log, setLog] = useState<LogRow[]>([]);

    async function loadLog() {
        const res = await fetch('/dashboard/customer-support/messaging/log');
        if (res.ok) setLog((await res.json()).data);
    }

    useEffect(() => { loadLog(); }, []);

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <SmsComposer
                endpoint="/dashboard/customer-support/messaging/sms"
                onSent={loadLog}
            />

            <div>
                <h3 className="mb-2 text-sm font-medium">Recent messages</h3>
                <ul className="space-y-2">
                    {log.map((m) => (
                        <li key={m.id} className="rounded border p-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="font-medium">{m.recipient?.name ?? m.to_phone}</span>
                                <Badge className={statusColors[m.status]}>{m.status}</Badge>
                            </div>
                            <p className="mt-1 text-slate-600">{m.body}</p>
                            <p className="mt-1 text-xs text-slate-400">{new Date(m.created_at).toLocaleString()} · {m.sender?.name ?? ''}</p>
                        </li>
                    ))}
                    {log.length === 0 && <p className="text-sm text-slate-500">No messages sent yet.</p>}
                </ul>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```
git add resources/js/Pages/admin/customer-support/messaging-tab.tsx
git commit -m "feat(customer-support): messaging tab (composer + log)"
```

---

## Task 20: Index page — Birthdays tab + final build

**Files:**
- Create: `resources/js/Pages/admin/customer-support/birthdays-tab.tsx`

- [ ] **Step 1: Write `birthdays-tab.tsx`**

```tsx
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type BirthdayUser = {
    id: number;
    name: string;
    role: string;
    phone: string | null;
    age_turning: number;
    birthday_date: string;
};

export function Birthdays() {
    const [today, setToday] = useState<BirthdayUser[]>([]);
    const [week, setWeek] = useState<BirthdayUser[]>([]);

    useEffect(() => {
        fetch('/dashboard/customer-support/birthdays')
            .then((r) => r.json())
            .then((data) => { setToday(data.today); setWeek(data.this_week); });
    }, []);

    function sendBirthday(user: BirthdayUser) {
        if (!user.phone) return;
        const body = `Hi ${user.name}, happy birthday from all of us at Surprise Moi! 🎉`;
        fetch('/dashboard/customer-support/messaging/sms', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                to_user_id: user.id,
                to_phone: user.phone,
                template_key: 'birthday',
                body,
            }),
        }).then(() => alert(`Birthday SMS sent to ${user.name}`));
    }

    function row(user: BirthdayUser) {
        return (
            <li key={user.id} className="flex items-center justify-between rounded border p-3 text-sm">
                <div>
                    <div className="font-medium">{user.name} <span className="text-xs text-slate-500">({user.role})</span></div>
                    <div className="text-xs text-slate-500">Turning {user.age_turning} on {user.birthday_date} · {user.phone ?? 'no phone'}</div>
                </div>
                <Button size="sm" disabled={!user.phone} onClick={() => sendBirthday(user)}>🎂 Send</Button>
            </li>
        );
    }

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h3 className="mb-2 text-sm font-medium">Today ({today.length})</h3>
                <ul className="space-y-2">{today.map(row)}{today.length === 0 && <p className="text-sm text-slate-500">No birthdays today.</p>}</ul>
            </div>
            <div>
                <h3 className="mb-2 text-sm font-medium">This week ({week.length})</h3>
                <ul className="space-y-2">{week.map(row)}{week.length === 0 && <p className="text-sm text-slate-500">None this week.</p>}</ul>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify build (full frontend)**

Run: `pnpm run build`
Expected: build succeeds, no TS errors. If any path is wrong (e.g. `app-sidebar-layout` import), fix by reading the actual layout file under `resources/js/layouts/app/`.

- [ ] **Step 3: Commit**

```
git add resources/js/Pages/admin/customer-support/birthdays-tab.tsx
git commit -m "feat(customer-support): birthdays tab with quick-send"
```

---

## Task 21: Manual smoke test in the browser

This task has no code — it verifies the feature actually works end-to-end. **Do not skip.** UI-correctness cannot be inferred from passing unit tests.

- [ ] **Step 1: Start the dev stack**

Run: `composer run dev` (the documented dev command in `CLAUDE.md`). If `composer.json` defines a different script, use that — read `composer.json` `scripts` section to confirm.

- [ ] **Step 2: Sign in as the super admin**

Use the credentials from `CLAUDE.md` (xylaray37@gmail.com / Gilash@123).

- [ ] **Step 3: Walk the golden path**

1. Sidebar → Support → Customer Support → land on the Tickets tab.
2. Click "New Ticket". Pick a registered user via the contact picker (verify auto-fill of phone/email). Submit. Land on the ticket detail page.
3. On the ticket page, click "Add Interaction" — log a phone call with a follow-up date. Verify it appears in the timeline.
4. Click "Send SMS" on the ticket. Type a body. Send. Verify a flash message appears, the message log on the Messaging tab shows the new row, and the timeline gains an outbound SMS interaction.
5. Click "Close". Provide a closure note. Submit. Status flips to closed; "Reopen" button appears.
6. Click "Reopen". Status flips back to open; closure note is preserved in the side panel.

- [ ] **Step 4: Walk the messaging tab**

1. Tab → Messaging.
2. Send a standalone SMS to a user (template: birthday). Verify it lands in the log with status `queued` initially, and (if Horizon is running and Kairos is reachable) flips to `sent`.
3. Try sending without a phone — verify the Send button stays disabled.

- [ ] **Step 5: Walk the birthdays tab**

1. Tab → Birthdays. The lists may be empty — that's fine.
2. To force-test: open `php artisan tinker` and update one user's `date_of_birth` to today's month/day. Refresh the tab. Verify the user appears in "Today" with a 🎂 button. Click Send.

- [ ] **Step 6: Auth boundary smoke test**

1. Log out. Log back in as a customer (any in the DB). Try navigating to `/dashboard/customer-support`. Confirm you are redirected away (the existing `dashboard` middleware handles this).

If anything breaks, fix it and re-test before moving on. **Do not claim the task is done unless every step above passes.** Capture screenshots if helpful.

- [ ] **Step 7: No commit**

This is verification. Nothing to commit unless you fixed a bug, in which case commit the fix with a clear message.

---

## Task 22: Run the full test suite + final cleanup

- [ ] **Step 1: Run all CustomerSupport tests**

Run: `php artisan test --compact --filter=CustomerSupport`
Expected: every test in the new feature passes.

- [ ] **Step 2: Run the full suite to catch regressions**

Run: `php artisan test --compact`
Expected: no new failures vs. baseline. If a previously-passing test now fails, that's a regression — investigate before moving on. Pre-existing failures (the codebase had 6 fixed in commit `5202776` recently) shouldn't be present anymore; if they are, surface them.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean. Commit any remaining formatting changes:

```
git add -u
git commit -m "chore(customer-support): apply pint formatting" --allow-empty
```

(Use `--allow-empty` only if there's literally nothing to commit — usually you'll have a small diff.)

- [ ] **Step 4: Final summary**

Confirm the branch contains:
- 3 migrations
- 3 models + 3 factories
- 4 controllers (`SupportTicketController`, `SupportInteractionController`, `SupportMessageController`, `SupportContactController`)
- 5 form requests
- 1 job (`SendSupportSmsJob`)
- 1 config file (`config/support_templates.php`)
- 3 Inertia pages (`index`, `create`, `show`) + 2 tab files (`messaging-tab`, `birthdays-tab`)
- 7 React components
- 5 test files
- Sidebar entry wired
- Wayfinder regenerated

`git log feat/customer-support --oneline | head -25` should show ~22 commits.
