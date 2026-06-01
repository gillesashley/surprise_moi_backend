<?php

namespace Tests\Feature\Api\V1;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end test covering the full Paystack payout lifecycle:
 * vendor request → admin process → OTP finalize → webhook confirmation.
 */
class PayoutEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendor;

    protected VendorBalance $balance;

    protected VendorPayoutDetail $payoutDetail;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paystack.webhook_secret', 'test_webhook_secret');

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->vendor = User::factory()->vendor()->create();
        $this->balance = VendorBalance::factory()->create([
            'vendor_id' => $this->vendor->id,
            'available_balance' => 500.00,
            'pending_balance' => 0,
            'total_earned' => 1000.00,
            'total_withdrawn' => 0,
        ]);
        $this->payoutDetail = VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
            'paystack_recipient_code' => 'RCP_test12345',
        ]);
    }

    /** @test */
    public function full_paystack_transfer_lifecycle(): void
    {
        // 1. Vendor requests payout
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 200,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $payoutId = $response->json('payout.id');
        $payout = PayoutRequest::findOrFail($payoutId);

        $this->assertEquals(PayoutRequest::STATUS_PENDING, $payout->status);
        $this->assertEquals(300.00, (float) $this->balance->fresh()->available_balance);

        // 2. Admin processes via Paystack
        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 100000]],
            ], 200),
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer requires OTP to continue',
                'data' => [
                    'transfer_code' => 'TRF_otp_test',
                    'reference' => $payout->request_number,
                    'status' => 'otp',
                    'id' => 99999,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutId}/process");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', true);

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PROCESSING, $payout->status);
        $this->assertEquals('TRF_otp_test', $payout->paystack_transfer_code);

        // 3. Admin finalizes with OTP
        Http::fake([
            'https://api.paystack.co/transfer/finalize_transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer has been queued',
                'data' => ['status' => 'pending', 'transfer_code' => 'TRF_otp_test'],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutId}/finalize", [
                'otp' => '123456',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Paystack webhook confirms transfer success
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'transfer_code' => 'TRF_otp_test',
                'reference' => $payout->request_number,
                'amount' => 20000, // pesewas
                'currency' => 'GHS',
                'status' => 'success',
            ],
        ];

        Http::fake();

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'x-paystack-signature' => hash_hmac('sha512', json_encode($payload), config('services.paystack.webhook_secret')),
        ]);

        $response->assertStatus(200);

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payout->status);
        $this->assertNotNull($payout->paid_at);

        $this->balance->refresh();
        $this->assertEquals(200.00, (float) $this->balance->total_withdrawn);
    }

    /** @test */
    public function process_rejects_non_pending_request(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_APPROVED,
            'amount' => 100,
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/process");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function process_rejects_missing_paystack_recipient(): void
    {
        $detail = VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
            'paystack_recipient_code' => null,
        ]);

        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 100,
            'payout_detail_id' => $detail->id,
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/process");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function process_blocks_when_paystack_balance_insufficient(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 500,
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 10000]], // 100 GHS < 500 GHS
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/process");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function rejection_refunds_available_balance(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 100,
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        $balanceBefore = $this->balance->available_balance;

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/reject", [
                'rejection_reason' => 'Invalid details',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_REJECTED, $payoutRequest->status);

        $this->balance->refresh();
        $this->assertEquals(
            (float) $balanceBefore + 100,
            (float) $this->balance->available_balance
        );
    }

    /** @test */
    public function transfer_failure_webhook_marks_failed_and_refunds(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PROCESSING,
            'amount' => 150,
            'paystack_transfer_code' => 'TRF_fail_test',
            'paystack_transfer_reference' => 'PYT-FAIL-REF001',
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        $availableBefore = $this->balance->available_balance;

        $payload = [
            'event' => 'transfer.failed',
            'data' => [
                'transfer_code' => 'TRF_fail_test',
                'reference' => 'PYT-FAIL-REF001',
                'amount' => 15000,
                'currency' => 'GHS',
                'status' => 'failed',
            ],
        ];

        Http::fake();

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'x-paystack-signature' => hash_hmac('sha512', json_encode($payload), config('services.paystack.webhook_secret')),
        ]);

        $response->assertStatus(200);

        $payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_FAILED, $payoutRequest->status);

        $this->balance->refresh();
        $this->assertEquals(
            (float) $availableBefore + 150,
            (float) $this->balance->available_balance
        );
    }

    /** @test */
    public function duplicate_transfer_success_webhook_is_idempotent(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PAID,
            'amount' => 200,
            'paystack_transfer_code' => 'TRF_dup_test',
            'paystack_transfer_reference' => 'PYT-DUP-REF001',
            'payout_detail_id' => $this->payoutDetail->id,
            'paid_at' => now(),
        ]);

        $this->balance->update(['total_withdrawn' => 200]);

        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'transfer_code' => 'TRF_dup_test',
                'reference' => 'PYT-DUP-REF001',
                'amount' => 20000,
                'currency' => 'GHS',
                'status' => 'success',
            ],
        ];

        Http::fake();

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'x-paystack-signature' => hash_hmac('sha512', json_encode($payload), config('services.paystack.webhook_secret')),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payoutRequest->fresh()->status);
        $this->assertEquals(200, (float) $this->balance->fresh()->total_withdrawn);
    }

    /** @test */
    public function vendor_blocked_by_duplicate_pending_request(): void
    {
        PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 100,
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 100,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function finalize_rejects_non_processing_payout(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 100,
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/finalize", [
                'otp' => '123456',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function paystack_balance_endpoint_returns_balance(): void
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
            ->assertJsonPath('success', true)
            ->assertJsonPath('balances.0.currency', 'GHS');
    }

    /** @test */
    public function process_transfer_without_otp_requirement(): void
    {
        $payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 100,
            'payout_detail_id' => $this->payoutDetail->id,
        ]);

        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'GHS', 'balance' => 100000]],
            ], 200),
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer has been queued',
                'data' => [
                    'transfer_code' => 'TRF_no_otp',
                    'reference' => $payoutRequest->request_number,
                    'status' => 'pending',
                    'id' => 88888,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payouts/{$payoutRequest->id}/process");

        $response->assertStatus(200)
            ->assertJsonPath('requires_otp', false);

        $payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PROCESSING, $payoutRequest->status);
    }
}
