<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
