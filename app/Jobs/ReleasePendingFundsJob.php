<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\VendorBalanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReleasePendingFundsJob implements ShouldQueue
{
    use Queueable;

    public function handle(VendorBalanceService $vendorBalanceService): void
    {
        $orders = Order::where('status', 'delivered')
            ->where('funds_released', false)
            ->where('funds_release_at', '<=', now())
            ->whereNotNull('vendor_id')
            ->whereNotNull('funds_release_at')
            ->get();

        foreach ($orders as $order) {
            try {
                $vendorBalanceService->releaseFunds($order);
                $order->update(['funds_released' => true]);
                Log::info("Funds released for order {$order->order_number}");
            } catch (\Exception $e) {
                Log::error("Failed to release funds for order {$order->order_number}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($orders->count() > 0) {
            Log::info("ReleasePendingFundsJob: Released funds for {$orders->count()} orders");
        }
    }
}
