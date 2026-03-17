# Paystack Payments Dashboard Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin dashboard page for browsing all Paystack payments, verifying them against the Paystack API, and syncing local state when divergence is detected.

**Architecture:** Local DB queries on existing `payments` and `vendor_onboarding_payments` tables with `UNION ALL` pagination. On-demand Paystack API verification via direct HTTP calls. Sync action reuses existing idempotent service methods.

**Tech Stack:** Laravel 12, Inertia v2, React 19, MUI, PostgreSQL, Paystack REST API

**Spec:** `docs/superpowers/specs/2026-03-17-paystack-payments-dashboard-design.md`

---

## File Structure

**Create:**
- `app/Http/Controllers/PaymentManagementController.php` — Admin controller (index, show, verify, sync)
- `app/Http/Requests/VerifyPaymentRequest.php` — Form Request for verify action
- `app/Http/Requests/SyncPaymentRequest.php` — Form Request for sync action
- `resources/js/pages/payments/index.tsx` — Payments list page
- `resources/js/pages/payments/show.tsx` — Payment detail page
- `tests/Feature/PaymentManagementTest.php` — Feature tests

**Modify:**
- `routes/web.php` — Add payment routes
- `resources/js/components/app-sidebar.tsx` — Add "Payments" nav item

---

## Task 1: Routes & Controller Scaffold

**Files:**
- Create: `app/Http/Controllers/PaymentManagementController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the controller via artisan**

Run: `docker compose exec -T app php artisan make:controller PaymentManagementController --no-interaction`

- [ ] **Step 2: Register routes in `routes/web.php`**

Add **before** the SPA catch-all route (`Route::get('/{any?}', ...)`), inside the existing `Route::middleware(['auth', 'dashboard'])->prefix('dashboard')` group. Place it after the Order Management route group (around line 155):

```php
// Payment Management
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentManagementController::class, 'index'])->name('index');
    Route::get('/{type}/{id}', [PaymentManagementController::class, 'show'])->name('show')
        ->whereIn('type', ['order', 'vendor-onboarding']);
    Route::post('/{type}/{id}/verify', [PaymentManagementController::class, 'verify'])->name('verify')
        ->whereIn('type', ['order', 'vendor-onboarding']);
    Route::post('/{type}/{id}/sync', [PaymentManagementController::class, 'sync'])->name('sync')
        ->whereIn('type', ['order', 'vendor-onboarding']);
});
```

Add the import at the top of the file:
```php
use App\Http\Controllers\PaymentManagementController;
```

- [ ] **Step 3: Scaffold the controller with empty methods**

Write `app/Http/Controllers/PaymentManagementController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncPaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Payment;
use App\Models\VendorOnboardingPayment;
use App\Services\PaystackService;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentManagementController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'success', 'failed', 'abandoned', 'reversed', 'cancelled'];

    public function index(Request $request): Response
    {
        // TODO: Task 2
        return Inertia::render('payments/index', []);
    }

    public function show(string $type, int $id): Response
    {
        // TODO: Task 3
        return Inertia::render('payments/show', []);
    }

    public function verify(VerifyPaymentRequest $request, string $type, int $id): JsonResponse
    {
        // TODO: Task 4
        return response()->json([]);
    }

    public function sync(SyncPaymentRequest $request, string $type, int $id): JsonResponse
    {
        // TODO: Task 4
        return response()->json([]);
    }
}
```

- [ ] **Step 4: Create Form Requests**

Run:
```bash
docker compose exec -T app php artisan make:request VerifyPaymentRequest --no-interaction
docker compose exec -T app php artisan make:request SyncPaymentRequest --no-interaction
```

Write `app/Http/Requests/VerifyPaymentRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [];
    }
}
```

Write `app/Http/Requests/SyncPaymentRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PaymentManagementController.php \
  app/Http/Requests/VerifyPaymentRequest.php \
  app/Http/Requests/SyncPaymentRequest.php \
  routes/web.php
git commit -m "feat: scaffold payment management controller and routes"
```

---

## Task 2: Controller — Index Action (UNION ALL Query)

**Files:**
- Modify: `app/Http/Controllers/PaymentManagementController.php`

- [ ] **Step 1: Write the failing test for index**

Create `tests/Feature/PaymentManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->vendor = User::factory()->vendor()->create();
    }

    public function test_admin_can_view_payments_index(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);
        Payment::factory()->successful()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);

        $application = VendorApplication::factory()->create([
            'user_id' => User::factory(),
        ]);
        VendorOnboardingPayment::factory()->successful()->create([
            'user_id' => $application->user_id,
            'vendor_application_id' => $application->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/payments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('payments/index')
            ->has('payments.data', 2)
            ->has('statuses')
            ->has('filters')
        );
    }

    public function test_non_admin_cannot_access_payments(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->get('/dashboard/payments');

        $response->assertRedirect();
    }

    public function test_payments_can_be_filtered_by_status(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);
        Payment::factory()->successful()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);
        Payment::factory()->pending()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/payments?status=success');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('payments/index')
            ->has('payments.data', 1)
        );
    }

    public function test_payments_can_be_filtered_by_type(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);
        Payment::factory()->successful()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);

        $application = VendorApplication::factory()->create([
            'user_id' => User::factory(),
        ]);
        VendorOnboardingPayment::factory()->successful()->create([
            'user_id' => $application->user_id,
            'vendor_application_id' => $application->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/payments?type=order');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('payments/index')
            ->has('payments.data', 1)
        );
    }

    public function test_payments_can_be_searched_by_reference(): void
    {
        $order = Order::factory()->pending()->create([
            'user_id' => User::factory(),
            'vendor_id' => $this->vendor->id,
            'coupon_id' => null,
        ]);
        Payment::factory()->successful()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'reference' => 'PAY-FINDTHISONE123',
        ]);
        Payment::factory()->pending()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'reference' => 'PAY-NOTTHISONE9876',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/payments?search=FINDTHIS');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('payments/index')
            ->has('payments.data', 1)
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --compact --filter=PaymentManagementTest`
Expected: FAIL (controller returns empty data)

- [ ] **Step 3: Implement the index action**

Replace the `index()` method in `PaymentManagementController`:

```php
public function index(Request $request): Response
{
    $type = $request->input('type');

    if ($type === 'order') {
        $query = $this->orderPaymentsQuery();
    } elseif ($type === 'vendor-onboarding') {
        $query = $this->vendorOnboardingPaymentsQuery();
    } else {
        $query = $this->unionQuery();
    }

    // Search
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('reference', 'ilike', "%{$search}%")
                ->orWhere('user_name', 'ilike', "%{$search}%")
                ->orWhere('user_email', 'ilike', "%{$search}%");
        });
    }

    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // Date range
    if ($request->filled('from')) {
        $query->where('created_at', '>=', $request->input('from'));
    }
    if ($request->filled('to')) {
        $query->where('created_at', '<=', $request->input('to').' 23:59:59');
    }

    // Sorting
    $sortBy = $request->input('sort_by', 'created_at');
    $sortOrder = $request->input('sort_order', 'desc');
    $allowedSorts = ['created_at', 'amount', 'status'];

    if (in_array($sortBy, $allowedSorts)) {
        $query->orderBy($sortBy, $sortOrder);
    } else {
        $query->orderBy('created_at', 'desc');
    }

    $payments = $query->paginate(15)->withQueryString();

    return Inertia::render('payments/index', [
        'payments' => $payments,
        'statuses' => self::STATUSES,
        'filters' => [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'type' => $type,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ],
    ]);
}

private function orderPaymentsQuery()
{
    return DB::table('payments')
        ->join('users', 'payments.user_id', '=', 'users.id')
        ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
        ->select([
            'payments.id',
            DB::raw("'order' as type"),
            'payments.reference',
            'users.name as user_name',
            'users.email as user_email',
            'payments.amount',
            'payments.currency',
            'payments.status',
            'payments.channel',
            'payments.paid_at',
            'payments.created_at',
            'orders.order_number as related_reference',
        ]);
}

private function vendorOnboardingPaymentsQuery()
{
    return DB::table('vendor_onboarding_payments')
        ->join('users', 'vendor_onboarding_payments.user_id', '=', 'users.id')
        ->select([
            'vendor_onboarding_payments.id',
            DB::raw("'vendor_onboarding' as type"),
            'vendor_onboarding_payments.reference',
            'users.name as user_name',
            'users.email as user_email',
            'vendor_onboarding_payments.amount',
            'vendor_onboarding_payments.currency',
            'vendor_onboarding_payments.status',
            'vendor_onboarding_payments.channel',
            'vendor_onboarding_payments.paid_at',
            'vendor_onboarding_payments.created_at',
            DB::raw("NULL as related_reference"),
        ]);
}

private function unionQuery()
{
    $orderQuery = $this->orderPaymentsQuery();
    $vendorQuery = $this->vendorOnboardingPaymentsQuery();

    return DB::query()->fromSub(
        $orderQuery->unionAll($vendorQuery),
        'payments_union'
    );
}
```

- [ ] **Step 4: Create a minimal React page so Inertia doesn't 500**

Create `resources/js/pages/payments/index.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/dashboard/payments' },
];

export default function PaymentsIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div>Payments index placeholder</div>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --compact --filter=PaymentManagementTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentManagementController.php \
  tests/Feature/PaymentManagementTest.php \
  resources/js/pages/payments/index.tsx
git commit -m "feat: implement payment management index with union query"
```

---

## Task 3: Controller — Show Action

**Files:**
- Modify: `app/Http/Controllers/PaymentManagementController.php`
- Modify: `tests/Feature/PaymentManagementTest.php`

- [ ] **Step 1: Write the failing tests for show**

Add to `PaymentManagementTest.php`:

```php
public function test_admin_can_view_order_payment_detail(): void
{
    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->successful()->card()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/dashboard/payments/order/{$payment->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('payments/show')
        ->has('payment')
        ->where('payment.type', 'order')
        ->where('payment.reference', $payment->reference)
    );
}

public function test_admin_can_view_vendor_onboarding_payment_detail(): void
{
    $application = VendorApplication::factory()->create([
        'user_id' => User::factory(),
    ]);
    $payment = VendorOnboardingPayment::factory()->successful()->create([
        'user_id' => $application->user_id,
        'vendor_application_id' => $application->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/dashboard/payments/vendor-onboarding/{$payment->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('payments/show')
        ->has('payment')
        ->where('payment.type', 'vendor_onboarding')
    );
}

public function test_show_returns_404_for_invalid_payment(): void
{
    $response = $this->actingAs($this->admin)
        ->get('/dashboard/payments/order/99999');

    $response->assertStatus(404);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --compact --filter=test_admin_can_view_order_payment_detail`
Expected: FAIL

- [ ] **Step 3: Implement the show action**

Replace the `show()` method in `PaymentManagementController`:

```php
public function show(string $type, int $id): Response
{
    $payment = $this->findPayment($type, $id);

    return Inertia::render('payments/show', [
        'payment' => $payment,
    ]);
}

private function findPayment(string $type, int $id): array
{
    if ($type === 'order') {
        $payment = Payment::with(['user:id,name,email,phone', 'order:id,order_number,status'])
            ->findOrFail($id);

        return [
            'id' => $payment->id,
            'type' => 'order',
            'reference' => $payment->reference,
            'paystack_reference' => $payment->paystack_reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'channel' => $payment->channel,
            'payment_method_type' => $payment->payment_method_type,
            'card_last4' => $payment->card_last4,
            'card_type' => $payment->card_type,
            'card_exp_month' => $payment->card_exp_month,
            'card_exp_year' => $payment->card_exp_year,
            'card_bank' => $payment->card_bank,
            'mobile_money_number' => $payment->mobile_money_number,
            'mobile_money_provider' => $payment->mobile_money_provider,
            'gateway_response' => $payment->gateway_response,
            'failure_reason' => $payment->failure_reason,
            'ip_address' => $payment->ip_address,
            'metadata' => $payment->metadata,
            'paid_at' => $payment->paid_at,
            'verified_at' => $payment->verified_at,
            'created_at' => $payment->created_at,
            'user' => $payment->user ? [
                'id' => $payment->user->id,
                'name' => $payment->user->name,
                'email' => $payment->user->email,
                'phone' => $payment->user->phone,
            ] : null,
            'related' => $payment->order ? [
                'order_number' => $payment->order->order_number,
                'order_status' => $payment->order->status,
                'order_id' => $payment->order->id,
            ] : null,
        ];
    }

    $payment = VendorOnboardingPayment::with([
        'user:id,name,email,phone',
        'vendorApplication:id,status,completed_step,current_step',
    ])->findOrFail($id);

    return [
        'id' => $payment->id,
        'type' => 'vendor_onboarding',
        'reference' => $payment->reference,
        'paystack_reference' => $payment->paystack_reference,
        'amount' => (string) $payment->amount,
        'currency' => $payment->currency,
        'status' => $payment->status,
        'channel' => $payment->channel,
        'payment_method_type' => $payment->payment_method_type,
        'card_last4' => $payment->card_last4,
        'card_type' => $payment->card_type,
        'card_exp_month' => $payment->card_exp_month,
        'card_exp_year' => $payment->card_exp_year,
        'card_bank' => $payment->card_bank,
        'mobile_money_number' => $payment->mobile_money_number,
        'mobile_money_provider' => $payment->mobile_money_provider,
        'gateway_response' => $payment->gateway_response,
        'failure_reason' => $payment->failure_reason,
        'ip_address' => $payment->ip_address,
        'metadata' => $payment->metadata,
        'paid_at' => $payment->paid_at,
        'verified_at' => $payment->verified_at,
        'created_at' => $payment->created_at,
        'user' => $payment->user ? [
            'id' => $payment->user->id,
            'name' => $payment->user->name,
            'email' => $payment->user->email,
            'phone' => $payment->user->phone,
        ] : null,
        'related' => $payment->vendorApplication ? [
            'application_id' => $payment->vendorApplication->id,
            'application_status' => $payment->vendorApplication->status,
            'current_step' => $payment->vendorApplication->current_step,
            'completed_step' => $payment->vendorApplication->completed_step,
        ] : null,
    ];
}
```

- [ ] **Step 4: Create minimal show React page**

Create `resources/js/pages/payments/show.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/dashboard/payments' },
    { title: 'Payment Detail', href: '#' },
];

export default function PaymentShow() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Detail" />
            <div>Payment show placeholder</div>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --compact --filter=PaymentManagementTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentManagementController.php \
  tests/Feature/PaymentManagementTest.php \
  resources/js/pages/payments/show.tsx
git commit -m "feat: implement payment management show action"
```

---

## Task 4: Controller — Verify & Sync Actions

**Files:**
- Modify: `app/Http/Controllers/PaymentManagementController.php`
- Modify: `tests/Feature/PaymentManagementTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `PaymentManagementTest.php`:

```php
public function test_admin_can_verify_order_payment_against_paystack(): void
{
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'status' => 'success',
                'reference' => 'PAY-TESTREF12345678',
                'amount' => 10000,
                'currency' => 'GHS',
                'channel' => 'mobile_money',
                'paid_at' => '2026-03-17T10:00:00.000Z',
                'gateway_response' => 'Successful',
                'authorization' => [
                    'last4' => '1234',
                    'bank' => 'MTN',
                    'channel' => 'mobile_money',
                ],
            ],
        ], 200),
    ]);

    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'reference' => 'PAY-TESTREF12345678',
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/dashboard/payments/order/{$payment->id}/verify");

    $response->assertStatus(200)
        ->assertJsonPath('paystack_data.data.status', 'success');

    // Verify local payment was NOT updated
    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'pending',
    ]);
}

public function test_verify_handles_paystack_api_failure(): void
{
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([], 500),
    ]);

    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/dashboard/payments/order/{$payment->id}/verify");

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
}

public function test_verify_handles_paystack_reference_not_found(): void
{
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => false,
            'message' => 'Transaction reference not found',
        ], 404),
    ]);

    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/dashboard/payments/order/{$payment->id}/verify");

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Failed to verify payment with Paystack.');
}

public function test_admin_can_sync_pending_payment_that_paystack_confirms(): void
{
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'status' => 'success',
                'reference' => 'PAY-SYNCTEST123456',
                'amount' => 10000,
                'currency' => 'GHS',
                'channel' => 'card',
                'paid_at' => '2026-03-17T10:00:00.000Z',
                'gateway_response' => 'Successful',
                'authorization' => [
                    'last4' => '4081',
                    'card_type' => 'visa',
                    'bank' => 'TEST BANK',
                    'channel' => 'card',
                ],
                'log' => null,
            ],
        ], 200),
    ]);

    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'reference' => 'PAY-SYNCTEST123456',
        'amount' => 100.00,
        'amount_in_kobo' => 10000,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/dashboard/payments/order/{$payment->id}/sync");

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'success',
    ]);
}

public function test_sync_is_idempotent_for_already_successful_payment(): void
{
    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->successful()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/dashboard/payments/order/{$payment->id}/sync");

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
}

public function test_non_admin_cannot_verify_payment(): void
{
    // Non-admin roles are redirected by the dashboard middleware before reaching the controller
    $customer = User::factory()->create(['role' => 'customer']);
    $order = Order::factory()->pending()->create([
        'user_id' => User::factory(),
        'vendor_id' => $this->vendor->id,
        'coupon_id' => null,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    $response = $this->actingAs($customer)
        ->postJson("/dashboard/payments/order/{$payment->id}/verify");

    $response->assertRedirect();
}
```

Add at top of test file:
```php
use Illuminate\Support\Facades\Http;
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --compact --filter=test_admin_can_verify`
Expected: FAIL

- [ ] **Step 3: Implement verify and sync actions**

Replace the `verify()` and `sync()` methods in `PaymentManagementController`:

```php
public function verify(VerifyPaymentRequest $request, string $type, int $id): JsonResponse
{
    $payment = $type === 'order'
        ? Payment::findOrFail($id)
        : VendorOnboardingPayment::findOrFail($id);

    $baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
    $secretKey = config('services.paystack.secret_key');

    try {
        $response = Http::withToken($secretKey)
            ->timeout(30)
            ->get("{$baseUrl}/transaction/verify/{$payment->reference}");

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment with Paystack.',
                'error' => $response->json('message') ?? 'Unknown error',
            ], 422);
        }

        $paystackData = $response->json();

        return response()->json([
            'success' => true,
            'local_status' => $payment->status,
            'paystack_data' => $paystackData,
            'status_mismatch' => ($paystackData['data']['status'] ?? '') !== $payment->status,
        ]);
    } catch (\Exception $e) {
        Log::error('Paystack verification failed from admin', [
            'payment_type' => $type,
            'payment_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Could not reach Paystack. Try again later.',
        ], 422);
    }
}

public function sync(SyncPaymentRequest $request, string $type, int $id): JsonResponse
{
    if ($type === 'order') {
        $payment = Payment::findOrFail($id);
        $beforeStatus = $payment->status;

        $paystackService = app(PaystackService::class);
        $result = $paystackService->verifyTransaction($payment->reference);
    } else {
        $payment = VendorOnboardingPayment::findOrFail($id);
        $beforeStatus = $payment->status;

        $paymentService = app(VendorOnboardingPaymentService::class);
        $result = $paymentService->verifyPayment($payment);
    }

    Log::info('Admin payment sync performed', [
        'admin_id' => $request->user()->id,
        'admin_email' => $request->user()->email,
        'payment_type' => $type,
        'payment_id' => $id,
        'reference' => $payment->reference,
        'status_before' => $beforeStatus,
        'status_after' => $payment->fresh()->status,
        'result' => $result['success'],
    ]);

    return response()->json([
        'success' => $result['success'],
        'message' => $result['message'],
        'payment' => $this->findPayment($type, $id),
    ]);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -T app php artisan test --compact --filter=PaymentManagementTest`
Expected: PASS

- [ ] **Step 5: Run pint**

Run: `docker compose exec -T app vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentManagementController.php \
  tests/Feature/PaymentManagementTest.php
git commit -m "feat: implement verify and sync actions for payment management"
```

---

## Task 5: Sidebar Navigation

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Add "Payments" to the Financial nav group**

In `resources/js/components/app-sidebar.tsx`, find the Financial section items array (around line 99-123). Add a new item after "All Transactions":

```tsx
{
    title: 'All Transactions',
    href: '/dashboard/transactions',
    icon: List,
},
{
    title: 'Payments',
    href: '/dashboard/payments',
    icon: Receipt,
},
```

Add `Receipt` to the lucide-react import at the top of the file.

- [ ] **Step 2: Verify the sidebar renders**

Run: `docker compose exec -T app pnpm run build`
Check for build errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/app-sidebar.tsx
git commit -m "feat: add Payments to admin sidebar navigation"
```

---

## Task 6: Frontend — Payments Index Page

**Files:**
- Modify: `resources/js/pages/payments/index.tsx`

- [ ] **Step 1: Implement the full payments index page**

Replace `resources/js/pages/payments/index.tsx` with the full implementation. Follow the exact patterns from `resources/js/pages/orders/index.tsx`:

- TypeScript interfaces for the props and payment shape
- Search with 300ms debounce using `useState` + `useEffect`
- Status filter, type filter, and date range filters
- Table with `Box component="table"` pattern
- Status badges using `Box component="span"` with inline `sx` styles
- Clickable rows via `router.visit()`
- Pagination with Previous/Next

Key interface:
```tsx
interface Payment {
    id: number;
    type: 'order' | 'vendor_onboarding';
    reference: string;
    user_name: string;
    user_email: string;
    amount: string;
    currency: string;
    status: string;
    channel: string | null;
    paid_at: string | null;
    created_at: string;
    related_reference: string | null;
}
```

Status badge colors:
```tsx
const statusColors: Record<string, { bg: string; color: string }> = {
    success: { bg: '#dcfce7', color: '#166534' },
    pending: { bg: '#fef9c3', color: '#854d0e' },
    processing: { bg: '#dbeafe', color: '#1e40af' },
    failed: { bg: '#fecaca', color: '#991b1b' },
    abandoned: { bg: '#f3f4f6', color: '#374151' },
    reversed: { bg: '#ffedd5', color: '#9a3412' },
    cancelled: { bg: '#e5e7eb', color: '#4b5563' },
};
```

Filter navigation pattern (from orders page):
```tsx
router.get('/dashboard/payments', params, {
    preserveState: true,
    preserveScroll: true,
});
```

- [ ] **Step 2: Build and verify**

Run: `docker compose exec -T app pnpm run build`
Expected: No errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/payments/index.tsx
git commit -m "feat: implement payments index page with filters and table"
```

---

## Task 7: Frontend — Payment Detail Page

**Files:**
- Modify: `resources/js/pages/payments/show.tsx`

- [ ] **Step 1: Implement the full payment detail page**

Replace `resources/js/pages/payments/show.tsx` with the full implementation. Follow patterns from `resources/js/pages/orders/show.tsx`:

- Four cards: Payment Overview, User & Related Entity, Technical Details, Paystack Verification
- Use `Card`, `CardHeader`, `CardTitle`, `CardContent` components
- Status badges with same color map as index page
- Metadata displayed in a collapsible `<pre>` block with `JSON.stringify(metadata, null, 2)`

Paystack Verification card state management:
```tsx
const [verifying, setVerifying] = useState(false);
const [paystackData, setPaystackData] = useState<any>(null);
const [verifyError, setVerifyError] = useState<string | null>(null);
const [showSyncDialog, setShowSyncDialog] = useState(false);
const [syncing, setSyncing] = useState(false);
```

Verify button handler — uses `axios` (already configured with CSRF in Laravel's `bootstrap.js`):
```tsx
import axios from 'axios';

const handleVerify = async () => {
    setVerifying(true);
    setVerifyError(null);
    try {
        const typeSlug = payment.type === 'order' ? 'order' : 'vendor-onboarding';
        const { data } = await axios.post(
            `/dashboard/payments/${typeSlug}/${payment.id}/verify`
        );
        if (data.success) {
            setPaystackData(data);
        } else {
            setVerifyError(data.message);
        }
    } catch (error: any) {
        setVerifyError(error.response?.data?.message ?? 'Could not reach Paystack. Try again later.');
    } finally {
        setVerifying(false);
    }
};
```

Sync button uses `router.post()` with Inertia for page refresh after sync:
```tsx
const handleSync = () => {
    setSyncing(true);
    router.post(
        `/dashboard/payments/${payment.type === 'order' ? 'order' : 'vendor-onboarding'}/${payment.id}/sync`,
        {},
        {
            onSuccess: () => {
                toast.success('Payment synced successfully');
                setShowSyncDialog(false);
                setPaystackData(null);
            },
            onError: () => toast.error('Failed to sync payment'),
            onFinish: () => setSyncing(false),
        }
    );
};
```

Status mismatch alert:
```tsx
{paystackData?.status_mismatch && (
    <Box sx={{ p: 2, bgcolor: '#fef3c7', borderRadius: 1, border: '1px solid #f59e0b', mb: 2 }}>
        <Typography fontWeight={600}>Status Mismatch Detected</Typography>
        <Typography variant="body2">
            Paystack reports <strong>{paystackData.paystack_data.data.status}</strong> but
            local status is <strong>{paystackData.local_status}</strong>
        </Typography>
    </Box>
)}
```

Confirmation dialog for sync:
```tsx
<Dialog open={showSyncDialog} onOpenChange={setShowSyncDialog}>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Sync Local Records</DialogTitle>
            <DialogDescription>
                This will update the local payment status and related records to match Paystack.
                The existing verification logic will run, which may trigger notifications.
            </DialogDescription>
        </DialogHeader>
        <DialogFooter>
            <Button variant="outline" onClick={() => setShowSyncDialog(false)}>Cancel</Button>
            <Button onClick={handleSync} disabled={syncing}>
                {syncing ? 'Syncing...' : 'Confirm Sync'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

- [ ] **Step 2: Build and verify**

Run: `docker compose exec -T app pnpm run build`
Expected: No errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/payments/show.tsx
git commit -m "feat: implement payment detail page with Paystack verification"
```

---

## Task 8: Pint & Final Verification

**Files:** All modified PHP files

- [ ] **Step 1: Run pint on all changed files**

Run: `docker compose exec -T app vendor/bin/pint --dirty --format agent`
Fix any formatting issues.

- [ ] **Step 2: Run the full test suite for this feature**

Run: `docker compose exec -T app php artisan test --compact --filter=PaymentManagementTest`
Expected: ALL PASS

- [ ] **Step 3: Build frontend**

Run: `docker compose exec -T app pnpm run build`
Expected: No errors

- [ ] **Step 4: Commit any pint fixes**

```bash
git add -A
git commit -m "style: apply pint formatting to payment management files"
```

- [ ] **Step 5: Ask user if they want to run the full test suite**

Ask: "All payment management tests pass. Want me to run the full test suite to make sure nothing else broke?"
