<?php

namespace Tests\Feature\Api\V1;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackTransferWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected VendorBalance $balance;

    protected PayoutRequest $payoutRequest;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paystack.webhook_secret', 'test_webhook_secret');

        $this->vendor = User::factory()->vendor()->create();
        $this->balance = VendorBalance::factory()->create([
            'vendor_id' => $this->vendor->id,
            'available_balance' => 0,
            'total_withdrawn' => 0,
        ]);
        $payoutDetail = VendorPayoutDetail::factory()->create(['vendor_id' => $this->vendor->id]);
        $this->payoutRequest = PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => PayoutRequest::STATUS_PROCESSING,
            'amount' => 500.00,
            'payout_detail_id' => $payoutDetail->id,
            'paystack_transfer_reference' => 'PYT-WEBHOOK001',
        ]);
    }

    public function test_transfer_success_webhook_marks_payout_as_paid(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'success',
                'amount' => 50000,
                'transfer_code' => 'TRF_test',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $this->payoutRequest->status);

        $this->balance->refresh();
        $this->assertEquals(500.00, (float) $this->balance->total_withdrawn);
    }

    public function test_transfer_failed_webhook_refunds_balance(): void
    {
        $payload = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'failed',
                'amount' => 50000,
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_FAILED, $this->payoutRequest->status);

        $this->balance->refresh();
        $this->assertEquals(500.00, (float) $this->balance->available_balance);
    }

    public function test_transfer_success_webhook_is_idempotent(): void
    {
        $this->payoutRequest->update([
            'status' => PayoutRequest::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'success',
                'amount' => 50000,
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Payout already processed.']);
    }

    public function test_transfer_failed_webhook_is_idempotent(): void
    {
        $this->payoutRequest->update(['status' => PayoutRequest::STATUS_FAILED]);

        $payload = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'failed',
                'amount' => 50000,
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Payout already processed.']);
    }

    public function test_transfer_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'success',
                'amount' => 50000,
            ],
        ];

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_transfer_success_creates_vendor_transaction(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'success',
                'amount' => 50000,
                'transfer_code' => 'TRF_test123',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $this->assertDatabaseHas('vendor_transactions', [
            'vendor_id' => $this->vendor->id,
            'type' => 'payout',
            'amount' => 500.00,
            'status' => 'completed',
        ]);
    }

    public function test_transfer_failed_creates_refund_transaction(): void
    {
        $payload = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'PYT-WEBHOOK001',
                'status' => 'failed',
                'amount' => 50000,
                'gateway_response' => 'Insufficient funds',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $this->assertDatabaseHas('vendor_transactions', [
            'vendor_id' => $this->vendor->id,
            'type' => 'refund',
            'amount' => 500.00,
            'status' => 'completed',
        ]);
    }

    public function test_transfer_webhook_with_unknown_reference_returns_not_found(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'UNKNOWN-REF-999',
                'status' => 'success',
                'amount' => 50000,
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'test_webhook_secret');

        $response = $this->postJson('/api/v1/payments/webhook', $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Payout request not found.']);
    }
}
