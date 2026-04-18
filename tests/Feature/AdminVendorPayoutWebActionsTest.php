<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the session-authenticated admin dashboard endpoints for vendor
 * payout actions. The API counterpart (Bearer-token auth) is tested in
 * Tests\Feature\Api\V1\AdminPayoutProcessingTest — this class specifically
 * guards the /dashboard/vendor-payouts/... routes the Inertia SPA calls.
 */
class AdminVendorPayoutWebActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendor;

    protected VendorBalance $balance;

    protected PayoutRequest $payoutRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->vendor = User::factory()->vendor()->create();
        $this->balance = VendorBalance::factory()->create([
            'vendor_id' => $this->vendor->id,
            'available_balance' => 4500.00,
            'total_withdrawn' => 0,
        ]);
        VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);
        $this->payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 500.00,
            'payout_method' => 'mobile_money',
        ]);
    }

    public function test_admin_can_approve_payout_via_web_route(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/approve", [
                'admin_notes' => 'Looks good, approved.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->payoutRequest->refresh();
        $this->assertSame(PayoutRequest::STATUS_APPROVED, $this->payoutRequest->status);
        $this->assertSame($this->admin->id, $this->payoutRequest->processed_by);
    }

    public function test_admin_can_reject_payout_via_web_route(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/reject", [
                'rejection_reason' => 'Suspicious account details.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->payoutRequest->refresh();
        $this->assertSame(PayoutRequest::STATUS_REJECTED, $this->payoutRequest->status);
    }

    public function test_reject_without_reason_flashes_validation_errors(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/reject", [
                'rejection_reason' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('rejection_reason');

        $this->assertSame(PayoutRequest::STATUS_PENDING, $this->payoutRequest->fresh()->status);
    }

    public function test_admin_can_mark_approved_payout_as_paid_via_web_route(): void
    {
        $this->payoutRequest->update(['status' => PayoutRequest::STATUS_APPROVED]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/mark-paid", [
                'payment_reference' => 'MOMO-REF-123456',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->payoutRequest->refresh();
        $this->assertSame(PayoutRequest::STATUS_PAID, $this->payoutRequest->status);
        $this->assertNotNull($this->payoutRequest->paid_at);
    }

    public function test_guest_cannot_approve_payout(): void
    {
        $response = $this->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/approve");

        $response->assertRedirect('/login');
        $this->assertSame(PayoutRequest::STATUS_PENDING, $this->payoutRequest->fresh()->status);
    }

    public function test_non_admin_cannot_approve_payout(): void
    {
        $response = $this->actingAs($this->vendor)
            ->post("/dashboard/vendor-payouts/{$this->payoutRequest->id}/approve");

        $response->assertRedirect();
        $this->assertSame(PayoutRequest::STATUS_PENDING, $this->payoutRequest->fresh()->status);
    }
}
