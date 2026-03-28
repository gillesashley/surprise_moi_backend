# Treasury Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a super-admin-only Treasury dashboard page with tabs for Paystack balance overview, transactions, settlements, and fund transfers to a configurable company bank account.

**Architecture:** Tabbed Inertia page (`treasury/index.tsx`) backed by a `TreasuryController` that reads from Paystack API through a cache layer (10-min TTL via `CacheService`). Transfers write directly to Paystack and record to a local `TreasuryTransfer` audit model. Company bank account is stored locally with Paystack recipient verification.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Recharts (already installed), Paystack API, shadcn/ui components (tabs, card, dialog, table, skeleton, input-otp, select, badge)

**Spec:** `docs/superpowers/specs/2026-03-20-treasury-dashboard-design.md`

---

## File Structure

### New Files (Backend)
- `app/Http/Controllers/TreasuryController.php` — controller for all treasury routes (tab pages + actions)
- `app/Models/CompanyBankAccount.php` — company bank account model
- `app/Models/TreasuryTransfer.php` — local audit trail for treasury transfers
- `database/migrations/XXXX_XX_XX_create_company_bank_accounts_table.php`
- `database/migrations/XXXX_XX_XX_create_treasury_transfers_table.php`
- `database/factories/CompanyBankAccountFactory.php`
- `database/factories/TreasuryTransferFactory.php`
- `app/Http/Requests/SaveCompanyBankAccountRequest.php`
- `app/Http/Requests/InitiateTransferRequest.php`
- `app/Http/Requests/FinalizeTransferRequest.php`
- `app/Http/Requests/ResendTransferOtpRequest.php`
- `app/Http/Requests/VerifyBankAccountRequest.php`

### New Files (Frontend)
- `resources/js/pages/treasury/index.tsx` — main Treasury page with tab routing

### New Files (Tests)
- `tests/Feature/TreasuryAccessTest.php`
- `tests/Feature/CompanyBankAccountTest.php`
- `tests/Feature/TreasuryTransferTest.php`
- `tests/Feature/TreasuryOverviewTest.php`
- `tests/Feature/TreasuryTransactionsTest.php`
- `tests/Feature/TreasurySettlementsTest.php`

### Modified Files
- `app/Services/PaystackService.php` — add new methods: `listTransactions`, `getTransactionTotals`, `listSettlements`, `listTransfers`, `getBalanceLedger`, `resendTransferOtp`
- `app/Services/CacheService.php` — add `TTL_TREASURY` constant and `flushTreasuryCaches()` method
- `routes/web.php` — add treasury routes before the SPA catch-all
- `resources/js/components/app-sidebar.tsx` — add Treasury nav item for super_admin
- `app/Providers/AppServiceProvider.php` — add `treasury-transfer` rate limiter

---

## Task 1: Database — Migrations, Models, Factories

**Files:**
- Create: `database/migrations/XXXX_create_company_bank_accounts_table.php`
- Create: `database/migrations/XXXX_create_treasury_transfers_table.php`
- Create: `app/Models/CompanyBankAccount.php`
- Create: `app/Models/TreasuryTransfer.php`
- Create: `database/factories/CompanyBankAccountFactory.php`
- Create: `database/factories/TreasuryTransferFactory.php`

- [ ] **Step 1: Create CompanyBankAccount migration**

Run: `php artisan make:migration create_company_bank_accounts_table --no-interaction`

Then edit the migration:

```php
Schema::create('company_bank_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('account_name');
    $table->string('account_number');
    $table->string('bank_code');
    $table->string('bank_name');
    $table->string('paystack_recipient_code')->nullable();
    $table->boolean('is_active')->default(false);
    $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    $table->index('is_active');
});
```

- [ ] **Step 2: Create TreasuryTransfer migration**

Run: `php artisan make:migration create_treasury_transfers_table --no-interaction`

Then edit the migration:

```php
Schema::create('treasury_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_bank_account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
    $table->decimal('amount', 12, 2);
    $table->integer('amount_in_pesewas');
    $table->string('paystack_transfer_code')->nullable();
    $table->string('paystack_reference')->unique();
    $table->enum('status', ['pending', 'otp_required', 'processing', 'success', 'failed', 'reversed'])->default('pending');
    $table->json('paystack_response')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index('status');
    $table->index('paystack_reference');
});
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate --no-interaction`
Expected: Both tables created successfully.

- [ ] **Step 4: Create CompanyBankAccount model**

Run: `php artisan make:model CompanyBankAccount --no-interaction`

Then edit `app/Models/CompanyBankAccount.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_code',
        'bank_name',
        'paystack_recipient_code',
        'is_active',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function treasuryTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransfer::class);
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Set this account as active, deactivating any other.
     */
    public function activate(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            static::where('is_active', true)->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 5: Create TreasuryTransfer model**

Run: `php artisan make:model TreasuryTransfer --no-interaction`

Then edit `app/Models/TreasuryTransfer.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TreasuryTransfer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_OTP_REQUIRED = 'otp_required';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'company_bank_account_id',
        'initiated_by',
        'amount',
        'amount_in_pesewas',
        'paystack_transfer_code',
        'paystack_reference',
        'status',
        'paystack_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paystack_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TreasuryTransfer $transfer) {
            if (empty($transfer->paystack_reference)) {
                $transfer->paystack_reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'TRS-' . strtoupper(Str::random(10));
        } while (static::where('paystack_reference', $reference)->exists());

        return $reference;
    }

    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
```

- [ ] **Step 6: Create CompanyBankAccountFactory**

Run: `php artisan make:factory CompanyBankAccountFactory --no-interaction`

Then edit `database/factories/CompanyBankAccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_name' => fake()->company(),
            'account_number' => fake()->numerify('##########'),
            'bank_code' => fake()->numerify('###'),
            'bank_name' => fake()->randomElement(['Access Bank', 'GTBank', 'First Bank', 'Zenith Bank']),
            'paystack_recipient_code' => 'RCP_' . fake()->bothify('??????????'),
            'is_active' => false,
            'added_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 7: Create TreasuryTransferFactory**

Run: `php artisan make:factory TreasuryTransferFactory --no-interaction`

Then edit `database/factories/TreasuryTransferFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasuryTransferFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 50000);

        return [
            'company_bank_account_id' => CompanyBankAccount::factory(),
            'initiated_by' => User::factory(),
            'amount' => $amount,
            'amount_in_pesewas' => (int) ($amount * 100),
            'paystack_transfer_code' => 'TRF_' . fake()->bothify('??????????'),
            'paystack_reference' => TreasuryTransfer::generateReference(),
            'status' => TreasuryTransfer::STATUS_PENDING,
            'paystack_response' => null,
            'completed_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_FAILED,
            'paystack_response' => ['message' => 'Transfer failed'],
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_PROCESSING,
        ]);
    }

    public function otpRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
        ]);
    }
}
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/*company_bank_accounts* database/migrations/*treasury_transfers* app/Models/CompanyBankAccount.php app/Models/TreasuryTransfer.php database/factories/CompanyBankAccountFactory.php database/factories/TreasuryTransferFactory.php
git commit -m "feat(treasury): add CompanyBankAccount and TreasuryTransfer models, migrations, factories"
```

---

## Task 2: CacheService & PaystackService Extensions

**Files:**
- Modify: `app/Services/CacheService.php`
- Modify: `app/Services/PaystackService.php`

- [ ] **Step 1: Add treasury cache constants and flush method to CacheService**

Edit `app/Services/CacheService.php`. Add after the existing TTL constants (after line 22):

```php
public const TTL_TREASURY = 600;         // 10 min — Paystack data refresh interval
```

Add a new method after the existing flush methods (after line 77):

```php
/** @var array<string> Registry of active treasury cache keys */
private static array $treasuryCacheKeys = [];

/**
 * Register a treasury cache key so it can be flushed later.
 */
public static function registerTreasuryCacheKey(string $key): void
{
    static::$treasuryCacheKeys[] = $key;

    // Also persist to cache so keys survive across requests
    $registered = Cache::get('treasury:registered_keys', []);
    $registered[] = $key;
    Cache::put('treasury:registered_keys', array_unique($registered), self::TTL_TREASURY * 2);
}

/**
 * Flush all caches related to treasury Paystack data.
 */
public static function flushTreasuryCaches(): void
{
    $keys = array_unique(array_merge(
        static::$treasuryCacheKeys,
        Cache::get('treasury:registered_keys', [])
    ));

    foreach ($keys as $key) {
        Cache::forget($key);
    }

    // Also clear known fixed keys
    Cache::forget('treasury:balance');
    Cache::forget('treasury:balance_ledger');
    Cache::forget('treasury:transaction_totals');
    Cache::forget('treasury:registered_keys');

    static::$treasuryCacheKeys = [];
}

/**
 * Flush treasury balance and transfer caches (after a transfer).
 */
public static function flushTreasuryTransferCaches(): void
{
    $keys = Cache::get('treasury:registered_keys', []);

    // Flush balance and transfer-related keys
    Cache::forget('treasury:balance');
    Cache::forget('treasury:balance_ledger');
    Cache::forget('treasury:transaction_totals');

    foreach ($keys as $key) {
        if (str_starts_with($key, 'treasury:transfers:')) {
            Cache::forget($key);
        }
    }
}
```

- [ ] **Step 2: Add new Paystack API methods to PaystackService**

Edit `app/Services/PaystackService.php`. Add the following methods at the end of the class (before the closing `}`):

```php
/**
 * List transactions from Paystack.
 *
 * @param array{from?: string, to?: string, status?: string, perPage?: int, page?: int} $filters
 * @return array{success: bool, data: array, meta: array}
 */
public function listTransactions(array $filters = []): array
{
    $query = array_filter([
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'status' => $filters['status'] ?? null,
        'perPage' => $filters['perPage'] ?? 20,
        'page' => $filters['page'] ?? 1,
    ]);

    $cacheKey = 'treasury:transactions:' . md5(json_encode($query));
    CacheService::registerTreasuryCacheKey($cacheKey);

    return Cache::remember($cacheKey, CacheService::TTL_TREASURY, function () use ($query) {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->get("{$this->baseUrl}/transaction", $query);

        if ($response->successful() && $response->json('status') === true) {
            return [
                'success' => true,
                'data' => $response->json('data', []),
                'meta' => $response->json('meta', []),
            ];
        }

        Log::warning('Paystack listTransactions failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'data' => [], 'meta' => []];
    });
}

/**
 * Get transaction totals from Paystack.
 *
 * @param array{from?: string, to?: string} $filters
 * @return array{success: bool, data: array}
 */
public function getTransactionTotals(array $filters = []): array
{
    $query = array_filter([
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
    ]);

    return Cache::remember('treasury:transaction_totals', CacheService::TTL_TREASURY, function () use ($query) {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->get("{$this->baseUrl}/transaction/totals", $query);

        if ($response->successful() && $response->json('status') === true) {
            return [
                'success' => true,
                'data' => $response->json('data', []),
            ];
        }

        Log::warning('Paystack getTransactionTotals failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'data' => []];
    });
}

/**
 * List settlements from Paystack.
 *
 * @param array{from?: string, to?: string, perPage?: int, page?: int} $filters
 * @return array{success: bool, data: array, meta: array}
 */
public function listSettlements(array $filters = []): array
{
    $query = array_filter([
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'perPage' => $filters['perPage'] ?? 20,
        'page' => $filters['page'] ?? 1,
    ]);

    $cacheKey = 'treasury:settlements:' . md5(json_encode($query));
    CacheService::registerTreasuryCacheKey($cacheKey);

    return Cache::remember($cacheKey, CacheService::TTL_TREASURY, function () use ($query) {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->get("{$this->baseUrl}/settlement", $query);

        if ($response->successful() && $response->json('status') === true) {
            return [
                'success' => true,
                'data' => $response->json('data', []),
                'meta' => $response->json('meta', []),
            ];
        }

        Log::warning('Paystack listSettlements failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'data' => [], 'meta' => []];
    });
}

/**
 * List transfers from Paystack.
 *
 * @param array{from?: string, to?: string, perPage?: int, page?: int} $filters
 * @return array{success: bool, data: array, meta: array}
 */
public function listTransfers(array $filters = []): array
{
    $query = array_filter([
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'perPage' => $filters['perPage'] ?? 20,
        'page' => $filters['page'] ?? 1,
    ]);

    $cacheKey = 'treasury:transfers:' . md5(json_encode($query));
    CacheService::registerTreasuryCacheKey($cacheKey);

    return Cache::remember($cacheKey, CacheService::TTL_TREASURY, function () use ($query) {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->get("{$this->baseUrl}/transfer", $query);

        if ($response->successful() && $response->json('status') === true) {
            return [
                'success' => true,
                'data' => $response->json('data', []),
                'meta' => $response->json('meta', []),
            ];
        }

        Log::warning('Paystack listTransfers failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'data' => [], 'meta' => []];
    });
}

/**
 * Get balance ledger from Paystack.
 *
 * @return array{success: bool, data: array}
 */
public function getBalanceLedger(): array
{
    return Cache::remember('treasury:balance_ledger', CacheService::TTL_TREASURY, function () {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->get("{$this->baseUrl}/balance/ledger");

        if ($response->successful() && $response->json('status') === true) {
            return [
                'success' => true,
                'data' => $response->json('data', []),
            ];
        }

        Log::warning('Paystack getBalanceLedger failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'data' => []];
    });
}

/**
 * Resend OTP for a pending transfer.
 *
 * @return array{success: bool, message: string}
 */
public function resendTransferOtp(string $transferCode): array
{
    $response = Http::withToken($this->secretKey)
        ->timeout(30)
        ->withOptions(['verify' => false])
        ->post("{$this->baseUrl}/transfer/resend_otp", [
            'transfer_code' => $transferCode,
        ]);

    if ($response->successful() && $response->json('status') === true) {
        return [
            'success' => true,
            'message' => $response->json('message', 'OTP resent successfully'),
        ];
    }

    Log::warning('Paystack resendTransferOtp failed', [
        'transfer_code' => $transferCode,
        'status' => $response->status(),
        'body' => $response->body(),
    ]);

    return [
        'success' => false,
        'message' => $response->json('message', 'Failed to resend OTP'),
    ];
}
```

- [ ] **Step 3: Extend webhook handlers for treasury transfers**

In `app/Services/PaystackService.php`, modify `handleTransferSuccess()`. The method starts with a `$reference = $data['reference'] ?? null;` extraction and a null guard (`if (! $reference)`). Add the treasury check **after** the existing null-reference guard, **before** the PayoutRequest lookup:

```php
// After the existing: if (! $reference) { return ...; }
// Add this block:
if (str_starts_with($reference, 'TRS-')) {
    return $this->handleTreasuryTransferSuccess($data);
}
```

Similarly, in `handleTransferFailed()`, add after the existing null-reference guard and before the PayoutRequest lookup:

```php
if (str_starts_with($reference, 'TRS-')) {
    return $this->handleTreasuryTransferFailed($data);
}
```

Then add these two new private methods:

```php
/**
 * Handle a successful treasury transfer webhook.
 */
private function handleTreasuryTransferSuccess(array $data): array
{
    $reference = $data['reference'] ?? '';
    $transfer = TreasuryTransfer::where('paystack_reference', $reference)->first();

    if (! $transfer) {
        Log::warning('Treasury transfer not found for success webhook', ['reference' => $reference]);
        return ['success' => false, 'message' => 'Treasury transfer not found.'];
    }

    if ($transfer->isSuccessful()) {
        return ['success' => true, 'message' => 'Treasury transfer already processed.'];
    }

    $transfer->update([
        'status' => TreasuryTransfer::STATUS_SUCCESS,
        'paystack_response' => $data,
        'completed_at' => now(),
    ]);

    CacheService::flushTreasuryTransferCaches();

    Log::info('Treasury transfer success processed', ['reference' => $reference, 'amount' => $transfer->amount]);

    return ['success' => true, 'message' => 'Treasury transfer marked as successful.'];
}

/**
 * Handle a failed treasury transfer webhook.
 */
private function handleTreasuryTransferFailed(array $data): array
{
    $reference = $data['reference'] ?? '';
    $transfer = TreasuryTransfer::where('paystack_reference', $reference)->first();

    if (! $transfer) {
        Log::warning('Treasury transfer not found for failed webhook', ['reference' => $reference]);
        return ['success' => false, 'message' => 'Treasury transfer not found.'];
    }

    if ($transfer->hasFailed()) {
        return ['success' => true, 'message' => 'Treasury transfer already marked as failed.'];
    }

    $transfer->update([
        'status' => TreasuryTransfer::STATUS_FAILED,
        'paystack_response' => $data,
    ]);

    CacheService::flushTreasuryTransferCaches();

    Log::warning('Treasury transfer failed', ['reference' => $reference, 'reason' => $data['reason'] ?? 'unknown']);

    return ['success' => true, 'message' => 'Treasury transfer marked as failed.'];
}
```

**Important:** Add `use App\Models\TreasuryTransfer;` and `use App\Services\CacheService;` to the imports at the top of `PaystackService.php` if not already present.

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: Files formatted successfully.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CacheService.php app/Services/PaystackService.php
git commit -m "feat(treasury): add Paystack API methods for transactions, settlements, transfers, and balance ledger"
```

---

## Task 3: Form Requests

**Files:**
- Create: `app/Http/Requests/SaveCompanyBankAccountRequest.php`
- Create: `app/Http/Requests/InitiateTransferRequest.php`
- Create: `app/Http/Requests/FinalizeTransferRequest.php`
- Create: `app/Http/Requests/ResendTransferOtpRequest.php`

- [ ] **Step 1: Create SaveCompanyBankAccountRequest**

Run: `php artisan make:request SaveCompanyBankAccountRequest --no-interaction`

Then edit `app/Http/Requests/SaveCompanyBankAccountRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCompanyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string', 'max:20'],
            'bank_code' => ['required', 'string', 'max:10'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_number.required' => 'Please enter the account number.',
            'bank_code.required' => 'Please select a bank.',
            'account_name.required' => 'The account name is required. Please verify the account first.',
        ];
    }
}
```

- [ ] **Step 2: Create InitiateTransferRequest**

Run: `php artisan make:request InitiateTransferRequest --no-interaction`

Then edit `app/Http/Requests/InitiateTransferRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter an amount to transfer.',
            'amount.min' => 'The transfer amount must be at least GHS 0.01.',
        ];
    }
}
```

- [ ] **Step 3: Create FinalizeTransferRequest**

Run: `php artisan make:request FinalizeTransferRequest --no-interaction`

Then edit `app/Http/Requests/FinalizeTransferRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'transfer_code' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transfer_code.required' => 'Transfer code is required.',
            'otp.required' => 'Please enter the OTP.',
            'otp.size' => 'The OTP must be exactly 6 digits.',
        ];
    }
}
```

- [ ] **Step 4: Create VerifyBankAccountRequest**

Run: `php artisan make:request VerifyBankAccountRequest --no-interaction`

Then edit `app/Http/Requests/VerifyBankAccountRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string'],
            'bank_code' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 5: Create ResendTransferOtpRequest**

Run: `php artisan make:request ResendTransferOtpRequest --no-interaction`

Then edit `app/Http/Requests/ResendTransferOtpRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendTransferOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'transfer_code' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/SaveCompanyBankAccountRequest.php app/Http/Requests/InitiateTransferRequest.php app/Http/Requests/FinalizeTransferRequest.php app/Http/Requests/ResendTransferOtpRequest.php app/Http/Requests/VerifyBankAccountRequest.php
git commit -m "feat(treasury): add form request validation classes"
```

---

## Task 4: Routes, Rate Limiter, TreasuryController

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `app/Http/Controllers/TreasuryController.php`

- [ ] **Step 1: Add rate limiter in AppServiceProvider**

Edit `app/Providers/AppServiceProvider.php`. Add to the `boot()` method:

```php
RateLimiter::for('treasury-transfer', function (Request $request) {
    return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
});
```

Add the necessary imports at the top of the file:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
```

- [ ] **Step 2: Create TreasuryController**

Run: `php artisan make:controller TreasuryController --no-interaction`

Then edit `app/Http/Controllers/TreasuryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeTransferRequest;
use App\Http\Requests\InitiateTransferRequest;
use App\Http\Requests\ResendTransferOtpRequest;
use App\Http\Requests\SaveCompanyBankAccountRequest;
use App\Http\Requests\VerifyBankAccountRequest;
use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Services\CacheService;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryController extends Controller
{
    public function __construct(protected PaystackService $paystackService) {}

    /**
     * Ensure only super_admin can access treasury.
     */
    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()->role === 'super_admin', 403);
    }

    /**
     * Overview tab — balance, transaction totals, recent transactions.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $balance = $this->paystackService->checkBalance();
        $totals = $this->paystackService->getTransactionTotals();
        $recentTransactions = $this->paystackService->listTransactions(['perPage' => 10, 'page' => 1]);

        return Inertia::render('treasury/index', [
            'tab' => 'overview',
            'balance' => $balance,
            'totals' => $totals,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Transactions tab — full transaction list with filters.
     */
    public function transactions(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $filters = $request->only(['from', 'to', 'status', 'page']);
        $filters['perPage'] = 20;
        $transactions = $this->paystackService->listTransactions($filters);

        return Inertia::render('treasury/index', [
            'tab' => 'transactions',
            'transactions' => $transactions,
            'filters' => $request->only(['from', 'to', 'status']),
        ]);
    }

    /**
     * Settlements tab — settlement history.
     */
    public function settlements(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $filters = $request->only(['from', 'to', 'page']);
        $filters['perPage'] = 20;
        $settlements = $this->paystackService->listSettlements($filters);

        return Inertia::render('treasury/index', [
            'tab' => 'settlements',
            'settlements' => $settlements,
            'filters' => $request->only(['from', 'to']),
        ]);
    }

    /**
     * Transfers tab — transfer history + bank account + transfer form.
     */
    public function transfers(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $balance = $this->paystackService->checkBalance();
        $bankAccount = CompanyBankAccount::getActive();
        $transferHistory = TreasuryTransfer::with('companyBankAccount', 'initiatedBy')
            ->latest()
            ->paginate(20);

        return Inertia::render('treasury/index', [
            'tab' => 'transfers',
            'balance' => $balance,
            'bankAccount' => $bankAccount,
            'transferHistory' => $transferHistory,
        ]);
    }

    /**
     * Bust cache for all treasury data.
     */
    public function refresh(): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        CacheService::flushTreasuryCaches();

        return back()->with('success', 'Treasury data refreshed.');
    }

    /**
     * Get the current company bank account.
     */
    public function bankAccount(): Response
    {
        $this->authorizeSuperAdmin();

        $bankAccount = CompanyBankAccount::getActive();
        $banks = $this->paystackService->getBanks('ghana');

        return Inertia::render('treasury/index', [
            'tab' => 'transfers',
            'bankAccount' => $bankAccount,
            'banks' => $banks,
            'showBankForm' => true,
        ]);
    }

    /**
     * Verify a bank account via Paystack.
     */
    public function verifyBankAccount(VerifyBankAccountRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeSuperAdmin();

        $result = $this->paystackService->resolveAccountNumber(
            $request->input('account_number'),
            $request->input('bank_code')
        );

        return response()->json($result);
    }

    /**
     * Save a company bank account (after Paystack verification).
     */
    public function saveBankAccount(SaveCompanyBankAccountRequest $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        // Create transfer recipient on Paystack
        $recipientResult = $this->paystackService->createTransferRecipient(
            $request->input('account_name'),
            $request->input('account_number'),
            $request->input('bank_code')
        );

        if (! $recipientResult['success']) {
            return back()->withErrors(['account_number' => $recipientResult['message'] ?? 'Failed to create transfer recipient on Paystack.']);
        }

        $recipientCode = $recipientResult['data']['recipient_code'] ?? null;

        $account = CompanyBankAccount::create([
            'account_name' => $request->input('account_name'),
            'account_number' => $request->input('account_number'),
            'bank_code' => $request->input('bank_code'),
            'bank_name' => $request->input('bank_name'),
            'paystack_recipient_code' => $recipientCode,
            'added_by' => auth()->id(),
        ]);

        $account->activate();

        return redirect()->route('treasury.transfers')
            ->with('success', 'Bank account saved and set as active.');
    }

    /**
     * Initiate a transfer to the active company bank account.
     */
    public function initiateTransfer(InitiateTransferRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeSuperAdmin();

        $bankAccount = CompanyBankAccount::getActive();
        if (! $bankAccount || ! $bankAccount->paystack_recipient_code) {
            return back()->withErrors(['amount' => 'No active bank account configured. Please set up a bank account first.']);
        }

        $amountGhs = (float) $request->input('amount');
        $amountPesewas = (int) round($amountGhs * 100);

        // Check balance before calling Paystack
        $balanceData = $this->paystackService->checkBalance();
        if (! $balanceData['success']) {
            return back()->withErrors(['amount' => 'Unable to verify Paystack balance. Please try again.']);
        }

        $availableBalance = $balanceData['data'][0]['balance'] ?? 0;
        if ($amountPesewas > $availableBalance) {
            $availableGhs = number_format($availableBalance / 100, 2);
            return back()->withErrors(['amount' => "Insufficient Paystack balance. Available: GHS {$availableGhs}"]);
        }

        // Create local audit record
        $transfer = TreasuryTransfer::create([
            'company_bank_account_id' => $bankAccount->id,
            'initiated_by' => auth()->id(),
            'amount' => $amountGhs,
            'amount_in_pesewas' => $amountPesewas,
            'status' => TreasuryTransfer::STATUS_PENDING,
        ]);

        // Call Paystack
        $result = $this->paystackService->initiateTransfer(
            $amountPesewas,
            $bankAccount->paystack_recipient_code,
            "Treasury transfer {$transfer->paystack_reference}",
            $transfer->paystack_reference
        );

        if (! $result['success']) {
            $transfer->update([
                'status' => TreasuryTransfer::STATUS_FAILED,
                'paystack_response' => $result,
            ]);

            return back()->withErrors(['amount' => $result['message'] ?? 'Transfer initiation failed.']);
        }

        $transferCode = $result['data']['transfer_code'] ?? '';

        $transfer->update([
            'paystack_transfer_code' => $transferCode,
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
            'paystack_response' => $result,
        ]);

        return response()->json([
            'success' => true,
            'transfer_code' => $transferCode,
            'message' => 'OTP sent. Please enter it to complete the transfer.',
        ]);
    }

    /**
     * Finalize a transfer with OTP.
     */
    public function finalizeTransfer(FinalizeTransferRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeSuperAdmin();

        $transferCode = $request->input('transfer_code');
        $otp = $request->input('otp');

        $transfer = TreasuryTransfer::where('paystack_transfer_code', $transferCode)->first();
        if (! $transfer) {
            return response()->json(['success' => false, 'message' => 'Transfer not found.'], 404);
        }

        $result = $this->paystackService->finalizeTransfer($transferCode, $otp);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to finalize transfer.',
            ], 422);
        }

        $transfer->update([
            'status' => TreasuryTransfer::STATUS_PROCESSING,
            'paystack_response' => $result,
        ]);

        CacheService::flushTreasuryTransferCaches();

        return response()->json([
            'success' => true,
            'message' => 'Transfer is being processed.',
        ]);
    }

    /**
     * Resend OTP for a pending transfer.
     */
    public function resendTransferOtp(ResendTransferOtpRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeSuperAdmin();

        $result = $this->paystackService->resendTransferOtp($request->input('transfer_code'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
```

- [ ] **Step 3: Add routes to web.php**

Edit `routes/web.php`. Add the treasury routes **before** the SPA catch-all route (before the `Route::get('/{any?}', ...)` line). Add this block inside the dashboard middleware group:

```php
// Treasury (super_admin only - enforced in controller)
Route::prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/', [TreasuryController::class, 'index'])->name('index');
    Route::get('/transactions', [TreasuryController::class, 'transactions'])->name('transactions');
    Route::get('/settlements', [TreasuryController::class, 'settlements'])->name('settlements');
    Route::get('/transfers', [TreasuryController::class, 'transfers'])->name('transfers');

    Route::post('/refresh', [TreasuryController::class, 'refresh'])->name('refresh');

    Route::get('/bank-account', [TreasuryController::class, 'bankAccount'])->name('bank-account');
    Route::post('/bank-account', [TreasuryController::class, 'saveBankAccount'])->name('bank-account.save');
    Route::post('/bank-account/verify', [TreasuryController::class, 'verifyBankAccount'])->name('bank-account.verify');

    Route::post('/transfer', [TreasuryController::class, 'initiateTransfer'])
        ->middleware('throttle:treasury-transfer')
        ->name('transfer.initiate');
    Route::post('/transfer/finalize', [TreasuryController::class, 'finalizeTransfer'])->name('transfer.finalize');
    Route::post('/transfer/resend-otp', [TreasuryController::class, 'resendTransferOtp'])->name('transfer.resend-otp');
});
```

Add the import at the top of `routes/web.php`:

```php
use App\Http\Controllers\TreasuryController;
```

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TreasuryController.php routes/web.php app/Providers/AppServiceProvider.php
git commit -m "feat(treasury): add TreasuryController, routes, and rate limiter"
```

---

## Task 5: Tests — Access Control & Bank Account

**Files:**
- Create: `tests/Feature/TreasuryAccessTest.php`
- Create: `tests/Feature/CompanyBankAccountTest.php`

- [ ] **Step 1: Create TreasuryAccessTest**

Run: `php artisan make:test TreasuryAccessTest --phpunit --no-interaction`

Then edit `tests/Feature/TreasuryAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('treasury.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_treasury_overview(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertOk();
    }

    public function test_admin_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertForbidden();
    }

    public function test_vendor_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);

        // EnsureDashboardAccess middleware logs out non-dashboard users and redirects to login
        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // EnsureDashboardAccess middleware logs out non-dashboard users and redirects to login
        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_all_treasury_tabs(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)->get(route('treasury.transactions'))->assertOk();
        $this->actingAs($user)->get(route('treasury.settlements'))->assertOk();
        $this->actingAs($user)->get(route('treasury.transfers'))->assertOk();
    }
}
```

- [ ] **Step 2: Run TreasuryAccessTest**

Run: `php artisan test --compact --filter=TreasuryAccessTest`
Expected: All tests pass. Note: The Paystack API calls in the controller will need to be mocked. If tests fail due to Paystack calls, add `Http::fake()` with appropriate responses at the beginning of each test that hits a tab route.

If needed, add to the test class:

```php
protected function setUp(): void
{
    parent::setUp();

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => [['balance' => 1000000, 'currency' => 'GHS']],
            'meta' => [],
        ], 200),
    ]);
}
```

- [ ] **Step 3: Create CompanyBankAccountTest**

Run: `php artisan make:test CompanyBankAccountTest --phpunit --no-interaction`

Then edit `tests/Feature/CompanyBankAccountTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CompanyBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [['balance' => 1000000, 'currency' => 'GHS']],
                'meta' => [],
            ], 200),
        ]);
    }

    public function test_super_admin_can_verify_bank_account(): void
    {
        Http::fake([
            '*/bank/resolve*' => Http::response([
                'status' => true,
                'data' => [
                    'account_number' => '0123456789',
                    'account_name' => 'John Doe',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.bank-account.verify'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
            ]);

        $response->assertOk();
    }

    public function test_super_admin_can_save_bank_account(): void
    {
        Http::fake([
            '*/transferrecipient' => Http::response([
                'status' => true,
                'data' => ['recipient_code' => 'RCP_abc123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
                'account_name' => 'John Doe',
            ]);

        $response->assertRedirect(route('treasury.transfers'));

        $this->assertDatabaseHas('company_bank_accounts', [
            'account_number' => '0123456789',
            'bank_code' => '058',
            'is_active' => true,
            'paystack_recipient_code' => 'RCP_abc123',
        ]);
    }

    public function test_only_one_account_can_be_active(): void
    {
        $first = CompanyBankAccount::factory()->active()->create([
            'added_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '9999999999',
                'bank_code' => '044',
                'bank_name' => 'Access Bank',
                'account_name' => 'Jane Doe',
            ]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertDatabaseHas('company_bank_accounts', [
            'account_number' => '9999999999',
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_save_bank_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
                'account_name' => 'John Doe',
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 4: Run CompanyBankAccountTest**

Run: `php artisan test --compact --filter=CompanyBankAccountTest`
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TreasuryAccessTest.php tests/Feature/CompanyBankAccountTest.php
git commit -m "test(treasury): add access control and bank account tests"
```

---

## Task 6: Tests — Transfer Flow

**Files:**
- Create: `tests/Feature/TreasuryTransferTest.php`

- [ ] **Step 1: Create TreasuryTransferTest**

Run: `php artisan make:test TreasuryTransferTest --phpunit --no-interaction`

Then edit `tests/Feature/TreasuryTransferTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected CompanyBankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->bankAccount = CompanyBankAccount::factory()->active()->create([
            'added_by' => $this->superAdmin->id,
            'paystack_recipient_code' => 'RCP_test123',
        ]);
    }

    public function test_super_admin_can_initiate_transfer(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 500000, 'currency' => 'GHS']],
            ], 200),
            '*/transfer' => Http::response([
                'status' => true,
                'data' => ['transfer_code' => 'TRF_abc123'],
                'message' => 'Transfer requires OTP',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'transfer_code' => 'TRF_abc123']);

        $this->assertDatabaseHas('treasury_transfers', [
            'initiated_by' => $this->superAdmin->id,
            'amount' => '1000.00',
            'amount_in_pesewas' => 100000,
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
        ]);
    }

    public function test_transfer_rejected_when_amount_exceeds_balance(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 5000, 'currency' => 'GHS']],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_transfer_rejected_without_active_bank_account(): void
    {
        $this->bankAccount->update(['is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.transfer.initiate'), [
                'amount' => 100.00,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_super_admin_can_finalize_transfer_with_otp(): void
    {
        $transfer = TreasuryTransfer::factory()->otpRequired()->create([
            'company_bank_account_id' => $this->bankAccount->id,
            'initiated_by' => $this->superAdmin->id,
            'paystack_transfer_code' => 'TRF_finalize123',
        ]);

        Http::fake([
            '*/transfer/finalize' => Http::response([
                'status' => true,
                'data' => ['status' => 'processing'],
                'message' => 'Transfer is being processed',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.finalize'), [
                'transfer_code' => 'TRF_finalize123',
                'otp' => '123456',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(
            TreasuryTransfer::STATUS_PROCESSING,
            $transfer->fresh()->status
        );
    }

    public function test_super_admin_can_resend_otp(): void
    {
        Http::fake([
            '*/transfer/resend_otp' => Http::response([
                'status' => true,
                'message' => 'OTP resent',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.resend-otp'), [
                'transfer_code' => 'TRF_resend123',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_treasury_transfer_audit_record_created_on_failure(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 500000, 'currency' => 'GHS']],
            ], 200),
            '*/transfer' => Http::response([
                'status' => false,
                'message' => 'Transfer failed',
            ], 400),
        ]);

        $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $this->assertDatabaseHas('treasury_transfers', [
            'initiated_by' => $this->superAdmin->id,
            'status' => TreasuryTransfer::STATUS_FAILED,
        ]);
    }

    public function test_admin_cannot_initiate_transfer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 100.00,
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run TreasuryTransferTest**

Run: `php artisan test --compact --filter=TreasuryTransferTest`
Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/TreasuryTransferTest.php
git commit -m "test(treasury): add transfer flow tests"
```

---

## Task 7: Frontend — Treasury Page (Overview & Transactions Tabs)

**Files:**
- Create: `resources/js/pages/treasury/index.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Add Treasury to sidebar**

Edit `resources/js/components/app-sidebar.tsx`. Add `Vault` (or `Landmark`) to the lucide-react import. Then, in the `getNavItemsForRole` function, after the "Content Management" item (line ~166) and before `return items` (line ~169), add:

```typescript
if (role === 'super_admin') {
    items.push({
        title: 'Treasury',
        href: '/dashboard/treasury',
        icon: Landmark,
    });
}
```

- [ ] **Step 2: Create the Treasury page**

Create `resources/js/pages/treasury/index.tsx`. This is a large file — it contains all four tabs in one component, using Inertia `<Link>` for tab navigation:

```tsx
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    Building2,
    DollarSign,
    Landmark,
    RefreshCw,
    Send,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Treasury', href: '/dashboard/treasury' },
];

interface TreasuryProps {
    tab: 'overview' | 'transactions' | 'settlements' | 'transfers';
    balance?: { success: boolean; data: Array<{ balance: number; currency: string }> };
    totals?: { success: boolean; data: Record<string, unknown> };
    recentTransactions?: { success: boolean; data: Array<Record<string, unknown>>; meta: Record<string, unknown> };
    transactions?: { success: boolean; data: Array<Record<string, unknown>>; meta: Record<string, unknown> };
    settlements?: { success: boolean; data: Array<Record<string, unknown>>; meta: Record<string, unknown> };
    transferHistory?: {
        data: Array<{
            id: number;
            amount: string;
            status: string;
            paystack_reference: string;
            paystack_transfer_code: string;
            created_at: string;
            completed_at: string | null;
            company_bank_account: { bank_name: string; account_number: string } | null;
            initiated_by: { name: string } | null;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    bankAccount?: {
        id: number;
        account_name: string;
        account_number: string;
        bank_code: string;
        bank_name: string;
        paystack_recipient_code: string;
    } | null;
    banks?: { success: boolean; data: Array<{ name: string; code: string }> };
    filters?: Record<string, string>;
    showBankForm?: boolean;
}

const tabs = [
    { key: 'overview', label: 'Overview', href: '/dashboard/treasury' },
    { key: 'transactions', label: 'Transactions', href: '/dashboard/treasury/transactions' },
    { key: 'settlements', label: 'Settlements', href: '/dashboard/treasury/settlements' },
    { key: 'transfers', label: 'Transfers', href: '/dashboard/treasury/transfers' },
];

function formatGhs(pesewas: number): string {
    return `GHS ${(pesewas / 100).toLocaleString('en-GH', { minimumFractionDigits: 2 })}`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatusBadge({ status }: { status: string }) {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        success: 'default',
        processing: 'secondary',
        pending: 'outline',
        otp_required: 'outline',
        failed: 'destructive',
        reversed: 'destructive',
        abandoned: 'destructive',
    };

    return <Badge variant={variants[status] ?? 'outline'}>{status}</Badge>;
}

// ============ OVERVIEW TAB ============
function OverviewTab({ balance, totals, recentTransactions }: TreasuryProps) {
    const balanceAmount = balance?.data?.[0]?.balance ?? 0;

    const handleRefresh = () => {
        router.post('/dashboard/treasury/refresh', {}, {
            preserveScroll: true,
            onFinish: () => router.reload(),
        });
    };

    return (
        <div className="space-y-6">
            {/* Balance Card */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardDescription>Paystack Balance</CardDescription>
                        <CardTitle className="text-3xl">{formatGhs(balanceAmount)}</CardTitle>
                    </div>
                    <Button variant="outline" size="sm" onClick={handleRefresh}>
                        <RefreshCw className="mr-2 h-4 w-4" /> Refresh
                    </Button>
                </CardHeader>
            </Card>

            {/* Quick Stats */}
            {totals?.success && (
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Transactions</CardDescription>
                            <CardTitle className="text-2xl">
                                {(totals.data as Record<string, number>)?.total_transactions?.toLocaleString() ?? '0'}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Volume</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatGhs((totals.data as Record<string, number>)?.total_volume ?? 0)}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Pending</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatGhs((totals.data as Record<string, number>)?.pending_amount ?? 0)}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>
            )}

            {/* Recent Transactions */}
            <Card>
                <CardHeader>
                    <CardTitle>Recent Transactions</CardTitle>
                </CardHeader>
                <CardContent>
                    {recentTransactions?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentTransactions.data.slice(0, 10).map((tx, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{formatDate(tx.created_at as string)}</TableCell>
                                        <TableCell className="font-mono text-xs">{tx.reference as string}</TableCell>
                                        <TableCell>{formatGhs(tx.amount as number)}</TableCell>
                                        <TableCell><StatusBadge status={tx.status as string} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">No recent transactions.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

// ============ TRANSACTIONS TAB ============
function TransactionsTab({ transactions, filters }: TreasuryProps) {
    const [from, setFrom] = useState(filters?.from ?? '');
    const [to, setTo] = useState(filters?.to ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');

    const handleFilter = () => {
        router.get('/dashboard/treasury/transactions', { from, to, status }, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            {/* Filters */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <Label>From</Label>
                            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </div>
                        <div>
                            <Label>To</Label>
                            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="success">Success</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                    <SelectItem value="abandoned">Abandoned</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={handleFilter}>Filter</Button>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="pt-6">
                    {transactions?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Channel</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.map((tx, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{formatDate(tx.created_at as string)}</TableCell>
                                        <TableCell className="font-mono text-xs">{tx.reference as string}</TableCell>
                                        <TableCell>{(tx.customer as Record<string, string>)?.email ?? '-'}</TableCell>
                                        <TableCell>{formatGhs(tx.amount as number)}</TableCell>
                                        <TableCell>{(tx.channel as string) ?? '-'}</TableCell>
                                        <TableCell><StatusBadge status={tx.status as string} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">No transactions found.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

// ============ SETTLEMENTS TAB ============
function SettlementsTab({ settlements, filters }: TreasuryProps) {
    const [from, setFrom] = useState(filters?.from ?? '');
    const [to, setTo] = useState(filters?.to ?? '');

    const handleFilter = () => {
        router.get('/dashboard/treasury/settlements', { from, to }, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <Label>From</Label>
                            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </div>
                        <div>
                            <Label>To</Label>
                            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                        </div>
                        <Button onClick={handleFilter}>Filter</Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    {settlements?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {settlements.data.map((s, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{formatDate((s.settled_date ?? s.created_at) as string)}</TableCell>
                                        <TableCell>{formatGhs(s.total_amount as number)}</TableCell>
                                        <TableCell><StatusBadge status={s.status as string} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">No settlements found.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

// ============ TRANSFERS TAB ============
function TransfersTab({ balance, bankAccount, transferHistory }: TreasuryProps) {
    const [amount, setAmount] = useState('');
    const [transferring, setTransferring] = useState(false);
    const [showOtpModal, setShowOtpModal] = useState(false);
    const [transferCode, setTransferCode] = useState('');
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [showBankForm, setShowBankForm] = useState(false);
    const [bankFormData, setBankFormData] = useState({ account_number: '', bank_code: '', bank_name: '' });
    const [verifiedName, setVerifiedName] = useState('');
    const [recipientCode, setRecipientCode] = useState('');
    const [verifying, setVerifying] = useState(false);

    const balanceAmount = balance?.data?.[0]?.balance ?? 0;
    const balanceGhs = balanceAmount / 100;

    const handleUseFullBalance = () => setAmount(balanceGhs.toFixed(2));

    const handleInitiateTransfer = async () => {
        setTransferring(true);
        setError('');
        try {
            const response = await fetch('/dashboard/treasury/transfer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ amount: parseFloat(amount) }),
            });
            const result = await response.json();
            if (result.success) {
                setTransferCode(result.transfer_code);
                setShowOtpModal(true);
            } else {
                setError(result.message || result.errors?.amount?.[0] || 'Transfer failed.');
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setTransferring(false);
        }
    };

    const handleFinalizeTransfer = async () => {
        setTransferring(true);
        setError('');
        try {
            const response = await fetch('/dashboard/treasury/transfer/finalize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ transfer_code: transferCode, otp }),
            });
            const result = await response.json();
            if (result.success) {
                setShowOtpModal(false);
                setOtp('');
                setAmount('');
                router.reload();
            } else {
                setError(result.message || 'Failed to finalize transfer.');
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setTransferring(false);
        }
    };

    const handleResendOtp = async () => {
        try {
            await fetch('/dashboard/treasury/transfer/resend-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ transfer_code: transferCode }),
            });
        } catch { /* silently handle */ }
    };

    const handleVerifyAccount = async () => {
        setVerifying(true);
        setVerifiedName('');
        try {
            const response = await fetch('/dashboard/treasury/bank-account/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ account_number: bankFormData.account_number, bank_code: bankFormData.bank_code }),
            });
            const result = await response.json();
            if (result.success || result.data?.account_name) {
                setVerifiedName(result.data?.account_name ?? result.account_name ?? '');
                setRecipientCode(result.data?.recipient_code ?? '');
            }
        } catch { /* handle */ }
        finally { setVerifying(false); }
    };

    const handleSaveBankAccount = () => {
        router.post('/dashboard/treasury/bank-account', {
            account_number: bankFormData.account_number,
            bank_code: bankFormData.bank_code,
            bank_name: bankFormData.bank_name,
            account_name: verifiedName,
        });
    };

    return (
        <div className="space-y-6">
            {/* Bank Account Card */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" /> Company Bank Account
                        </CardTitle>
                        {bankAccount ? (
                            <CardDescription>
                                {bankAccount.account_name} - {bankAccount.bank_name} ({bankAccount.account_number})
                            </CardDescription>
                        ) : (
                            <CardDescription>No bank account configured.</CardDescription>
                        )}
                    </div>
                    <Button variant="outline" size="sm" onClick={() => setShowBankForm(true)}>
                        {bankAccount ? 'Change Account' : 'Set Up Account'}
                    </Button>
                </CardHeader>
            </Card>

            {/* Transfer Form */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Send className="h-5 w-5" /> Transfer Funds
                    </CardTitle>
                    <CardDescription>Current balance: {formatGhs(balanceAmount)}</CardDescription>
                </CardHeader>
                <CardContent>
                    {!bankAccount ? (
                        <p className="text-muted-foreground">Set up a bank account first to make transfers.</p>
                    ) : (
                        <div className="flex items-end gap-4">
                            <div className="flex-1">
                                <Label>Amount (GHS)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    placeholder="Enter amount"
                                />
                            </div>
                            <Button variant="outline" onClick={handleUseFullBalance}>
                                Use Full Balance
                            </Button>
                            <Button onClick={handleInitiateTransfer} disabled={transferring || !amount}>
                                {transferring ? 'Processing...' : 'Transfer'}
                            </Button>
                        </div>
                    )}
                    {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                </CardContent>
            </Card>

            {/* Transfer History */}
            <Card>
                <CardHeader>
                    <CardTitle>Transfer History</CardTitle>
                </CardHeader>
                <CardContent>
                    {transferHistory?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Bank</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transferHistory.data.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell>{formatDate(t.created_at)}</TableCell>
                                        <TableCell>GHS {parseFloat(t.amount).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</TableCell>
                                        <TableCell>{t.company_bank_account?.bank_name ?? '-'}</TableCell>
                                        <TableCell className="font-mono text-xs">{t.paystack_reference}</TableCell>
                                        <TableCell><StatusBadge status={t.status} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">No transfers yet.</p>
                    )}
                </CardContent>
            </Card>

            {/* OTP Modal */}
            <Dialog open={showOtpModal} onOpenChange={setShowOtpModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Enter OTP</DialogTitle>
                        <DialogDescription>
                            An OTP has been sent to the Paystack account owner. Enter it below to complete the transfer.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>OTP</Label>
                            <Input
                                value={otp}
                                onChange={(e) => setOtp(e.target.value)}
                                maxLength={6}
                                placeholder="Enter 6-digit OTP"
                            />
                        </div>
                        {error && <p className="text-sm text-red-600">{error}</p>}
                        <div className="flex justify-between">
                            <Button variant="ghost" size="sm" onClick={handleResendOtp}>
                                Resend OTP
                            </Button>
                            <Button onClick={handleFinalizeTransfer} disabled={transferring || otp.length !== 6}>
                                {transferring ? 'Confirming...' : 'Confirm Transfer'}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Bank Account Form Dialog */}
            <Dialog open={showBankForm} onOpenChange={setShowBankForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Configure Bank Account</DialogTitle>
                        <DialogDescription>
                            Enter the company bank account details. The account will be verified with Paystack.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Account Number</Label>
                            <Input
                                value={bankFormData.account_number}
                                onChange={(e) => setBankFormData(prev => ({ ...prev, account_number: e.target.value }))}
                                placeholder="Enter account number"
                            />
                        </div>
                        <div>
                            <Label>Bank</Label>
                            <Input
                                value={bankFormData.bank_name}
                                onChange={(e) => setBankFormData(prev => ({ ...prev, bank_name: e.target.value }))}
                                placeholder="Bank name"
                            />
                        </div>
                        <div>
                            <Label>Bank Code</Label>
                            <Input
                                value={bankFormData.bank_code}
                                onChange={(e) => setBankFormData(prev => ({ ...prev, bank_code: e.target.value }))}
                                placeholder="Bank code (e.g., 058)"
                            />
                        </div>
                        {!verifiedName && (
                            <Button onClick={handleVerifyAccount} disabled={verifying || !bankFormData.account_number || !bankFormData.bank_code}>
                                {verifying ? 'Verifying...' : 'Verify Account'}
                            </Button>
                        )}
                        {verifiedName && (
                            <div className="rounded border border-green-200 bg-green-50 p-3">
                                <p className="text-sm text-green-800">
                                    Account Name: <strong>{verifiedName}</strong>
                                </p>
                                <Button className="mt-2" onClick={handleSaveBankAccount}>
                                    Confirm & Save
                                </Button>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

// ============ MAIN COMPONENT ============
export default function Treasury(props: TreasuryProps) {
    const { tab } = props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Treasury" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <Landmark className="h-8 w-8" />
                    <h1 className="text-3xl font-bold">Treasury</h1>
                </div>

                {/* Tab Navigation */}
                <div className="flex gap-1 border-b">
                    {tabs.map((t) => (
                        <Link
                            key={t.key}
                            href={t.href}
                            preserveState
                            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                                tab === t.key
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </Link>
                    ))}
                </div>

                {/* Tab Content */}
                {tab === 'overview' && <OverviewTab {...props} />}
                {tab === 'transactions' && <TransactionsTab {...props} />}
                {tab === 'settlements' && <SettlementsTab {...props} />}
                {tab === 'transfers' && <TransfersTab {...props} />}
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Build frontend to check for errors**

Run: `pnpm run build`
Expected: Build succeeds with no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/treasury/index.tsx resources/js/components/app-sidebar.tsx
git commit -m "feat(treasury): add Treasury page with all tabs and sidebar navigation"
```

---

## Task 8: Tests — Overview, Transactions, Settlements Tabs

**Files:**
- Create: `tests/Feature/TreasuryOverviewTest.php`
- Create: `tests/Feature/TreasuryTransactionsTest.php`
- Create: `tests/Feature/TreasurySettlementsTest.php`

- [ ] **Step 1: Create TreasuryOverviewTest**

Run: `php artisan make:test TreasuryOverviewTest --phpunit --no-interaction`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 1500000, 'currency' => 'GHS']],
            ], 200),
            '*/transaction/totals*' => Http::response([
                'status' => true,
                'data' => [
                    'total_transactions' => 150,
                    'total_volume' => 5000000,
                    'pending_amount' => 200000,
                ],
            ], 200),
            '*/transaction*' => Http::response([
                'status' => true,
                'data' => [
                    ['reference' => 'REF_001', 'amount' => 50000, 'status' => 'success', 'created_at' => '2026-03-20T10:00:00Z'],
                ],
                'meta' => ['total' => 1],
            ], 200),
        ]);
    }

    public function test_overview_tab_renders_with_balance_and_totals(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('treasury.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->has('balance')
                ->has('totals')
                ->has('recentTransactions')
                ->where('tab', 'overview')
            );
    }

    public function test_refresh_clears_treasury_cache(): void
    {
        Cache::put('treasury:balance', ['data' => 'cached'], 600);

        $this->actingAs($this->superAdmin)
            ->post(route('treasury.refresh'))
            ->assertRedirect();

        $this->assertNull(Cache::get('treasury:balance'));
    }
}
```

- [ ] **Step 2: Create TreasuryTransactionsTest**

Run: `php artisan make:test TreasuryTransactionsTest --phpunit --no-interaction`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/transaction*' => Http::response([
                'status' => true,
                'data' => [
                    ['reference' => 'REF_001', 'amount' => 50000, 'status' => 'success', 'created_at' => '2026-03-20T10:00:00Z'],
                ],
                'meta' => ['total' => 1, 'page' => 1, 'pageCount' => 1],
            ], 200),
        ]);
    }

    public function test_transactions_tab_renders(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.transactions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->where('tab', 'transactions')
                ->has('transactions')
            );
    }

    public function test_transactions_tab_accepts_filters(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.transactions', ['from' => '2026-03-01', 'to' => '2026-03-20', 'status' => 'success']))
            ->assertOk();
    }
}
```

- [ ] **Step 3: Create TreasurySettlementsTest**

Run: `php artisan make:test TreasurySettlementsTest --phpunit --no-interaction`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasurySettlementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/settlement*' => Http::response([
                'status' => true,
                'data' => [
                    ['settled_date' => '2026-03-19', 'total_amount' => 300000, 'status' => 'success'],
                ],
                'meta' => ['total' => 1],
            ], 200),
        ]);
    }

    public function test_settlements_tab_renders(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.settlements'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->where('tab', 'settlements')
                ->has('settlements')
            );
    }
}
```

- [ ] **Step 4: Run all treasury tests**

Run: `php artisan test --compact --filter=Treasury`
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TreasuryOverviewTest.php tests/Feature/TreasuryTransactionsTest.php tests/Feature/TreasurySettlementsTest.php
git commit -m "test(treasury): add overview, transactions, and settlements tab tests"
```

---

## Task 9: Run Pint & Final Verification

**Files:** All modified files

- [ ] **Step 1: Run Pint on all dirty files**

Run: `vendor/bin/pint --dirty --format agent`
Expected: All files formatted.

- [ ] **Step 2: Run the full treasury test suite**

Run: `php artisan test --compact --filter=Treasury`
Expected: All tests pass.

- [ ] **Step 3: Run the CompanyBankAccount tests**

Run: `php artisan test --compact --filter=CompanyBankAccount`
Expected: All tests pass.

- [ ] **Step 4: Build frontend**

Run: `pnpm run build`
Expected: Build succeeds.

- [ ] **Step 5: Final commit (if any Pint changes)**

```bash
git add -A
git commit -m "style(treasury): apply Pint formatting"
```

- [ ] **Step 6: Ask user if they want to run the full test suite**

Prompt: "All treasury-specific tests pass. Would you like me to run the full test suite to make sure nothing else is broken?"
