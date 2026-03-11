<?php

namespace App\Jobs;

use App\Models\DeliveryRequest;
use App\Services\DeliveryDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastDeliveryRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public DeliveryRequest $deliveryRequest) {}

    /**
     * Execute the job.
     */
    public function handle(DeliveryDispatchService $dispatchService): void
    {
        if ($this->deliveryRequest->fresh()->status === 'accepted') {
            return;
        }

        $dispatchService->broadcastToNearbyRiders($this->deliveryRequest);

        $fresh = $this->deliveryRequest->fresh();
        if ($fresh && $fresh->status === 'broadcasting') {
            self::dispatch($fresh)->delay(now()->addSeconds(30));
        }
    }
}
