<?php

namespace Tests\Feature\Jobs;

use App\Jobs\VerifyPendingTransfersJob;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyPendingTransfersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_stuck_processing_payouts(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorBalance::factory()->create(['vendor_id' => $vendor->id, 'total_withdrawn' => 0]);
        $payoutDetail = VendorPayoutDetail::factory()->create(['vendor_id' => $vendor->id]);

        $payout = PayoutRequest::factory()->create([
            'user_id' => $vendor->id,
            'status' => PayoutRequest::STATUS_PROCESSING,
            'amount' => 200.00,
            'payout_detail_id' => $payoutDetail->id,
            'paystack_transfer_reference' => 'PYT-VERIFY001',
            'processed_at' => now()->subHours(2),
        ]);

        Http::fake([
            'https://api.paystack.co/transfer/verify/PYT-VERIFY001' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'reference' => 'PYT-VERIFY001'],
            ], 200),
        ]);

        $job = new VerifyPendingTransfersJob;
        $job->handle(app(\App\Services\PaystackService::class));

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payout->status);
    }

    public function test_does_not_verify_recent_processing_payouts(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorBalance::factory()->create(['vendor_id' => $vendor->id]);
        $payoutDetail = VendorPayoutDetail::factory()->create(['vendor_id' => $vendor->id]);

        $payout = PayoutRequest::factory()->create([
            'user_id' => $vendor->id,
            'status' => PayoutRequest::STATUS_PROCESSING,
            'amount' => 200.00,
            'payout_detail_id' => $payoutDetail->id,
            'paystack_transfer_reference' => 'PYT-RECENT001',
            'processed_at' => now()->subMinutes(30),
        ]);

        Http::fake();

        $job = new VerifyPendingTransfersJob;
        $job->handle(app(\App\Services\PaystackService::class));

        $payout->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PROCESSING, $payout->status);

        Http::assertNothingSent();
    }
}
