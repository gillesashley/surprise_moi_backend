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
