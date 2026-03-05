<?php

namespace App\Jobs;

use App\Models\PayoutRequest;
use App\Services\PaystackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyPendingTransfersJob implements ShouldQueue
{
    use Queueable;

    public function handle(PaystackService $paystackService): void
    {
        $stuckPayouts = PayoutRequest::where('status', PayoutRequest::STATUS_PROCESSING)
            ->where('processed_at', '<=', now()->subHour())
            ->whereNotNull('paystack_transfer_reference')
            ->get();

        foreach ($stuckPayouts as $payout) {
            try {
                $result = $paystackService->verifyTransfer($payout->paystack_transfer_reference);

                if (! $result['success']) {
                    Log::warning("Could not verify transfer for payout {$payout->request_number}");

                    continue;
                }

                $status = $result['data']['status'] ?? null;

                if ($status === 'success') {
                    $paystackService->handleWebhook([
                        'event' => 'transfer.success',
                        'data' => $result['data'],
                    ]);
                } elseif ($status === 'failed' || $status === 'reversed') {
                    $paystackService->handleWebhook([
                        'event' => 'transfer.failed',
                        'data' => $result['data'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error verifying transfer for payout {$payout->request_number}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($stuckPayouts->count() > 0) {
            Log::info("VerifyPendingTransfersJob: Checked {$stuckPayouts->count()} stuck payouts");
        }
    }
}
