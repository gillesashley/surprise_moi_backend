<?php

namespace Tests\Feature\Api\V1;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPayoutProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendor;

    protected VendorBalance $balance;

    protected VendorPayoutDetail $payoutDetail;

    protected PayoutRequest $payoutRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->vendor = User::factory()->vendor()->create();
        $this->balance = VendorBalance::factory()->create([
            'vendor_id' => $this->vendor->id,
            'available_balance' => 4500.00,
            'total_withdrawn' => 0,
        ]);
        $this->payoutDetail = VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);
        $this->payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 500.00,
            'payout_detail_id' => $this->payoutDetail->id,
            'payout_method' => 'mobile_money',
        ]);
    }

    public function test_admin_can_process_payout_via_paystack(): void
    {
        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 10000000]],
            ], 200),
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer requires OTP to continue',
                'data' => [
                    'transfer_code' => 'TRF_test123',
                    'reference' => $this->payoutRequest->request_number,
                    'status' => 'otp',
                    'id' => 12345,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$this->payoutRequest->id}/process");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', true);

        $this->payoutRequest->refresh();
        $this->assertEquals('processing', $this->payoutRequest->status);
        $this->assertEquals('TRF_test123', $this->payoutRequest->paystack_transfer_code);
    }

    public function test_admin_can_finalize_payout_with_otp(): void
    {
        $this->payoutRequest->update([
            'status' => PayoutRequest::STATUS_PROCESSING,
            'paystack_transfer_code' => 'TRF_test123',
        ]);

        Http::fake([
            'https://api.paystack.co/transfer/finalize_transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer has been queued',
                'data' => ['status' => 'pending', 'transfer_code' => 'TRF_test123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$this->payoutRequest->id}/finalize", [
                'otp' => '123456',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_process_fails_for_non_pending_payout(): void
    {
        $this->payoutRequest->update(['status' => 'paid']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$this->payoutRequest->id}/process");

        $response->assertStatus(400);
    }

    public function test_process_fails_when_paystack_balance_insufficient(): void
    {
        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 100]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$this->payoutRequest->id}/process");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_check_paystack_balance(): void
    {
        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 500000]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/paystack-balance');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reject_refunds_vendor_balance(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$this->payoutRequest->id}/reject", [
                'rejection_reason' => 'Invalid account details',
            ]);

        $response->assertStatus(200);

        $this->balance->refresh();
        $this->assertEquals(5000.00, (float) $this->balance->available_balance);
    }
}
