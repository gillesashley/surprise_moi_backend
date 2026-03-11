<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Events\DeliveryStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\CancelDeliveryRequest;
use App\Http\Requests\Api\Rider\V1\ConfirmDeliveryRequest;
use App\Http\Resources\Api\Rider\V1\DeliveryHistoryResource;
use App\Http\Resources\Api\Rider\V1\DeliveryRequestResource;
use App\Models\DeliveryRequest;
use App\Services\DeliveryDispatchService;
use App\Services\RiderBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryDispatchService $dispatchService,
        protected RiderBalanceService $balanceService,
    ) {}

    /**
     * Get incoming delivery requests available to the rider.
     */
    public function incoming(Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        $requests = DeliveryRequest::query()
            ->where(function ($q) use ($rider) {
                $q->where('status', 'broadcasting')
                    ->orWhere(function ($q2) use ($rider) {
                        $q2->where('status', 'assigned')
                            ->where('assigned_rider_id', $rider->id);
                    });
            })
            ->where('expires_at', '>', now())
            ->with(['order', 'vendor'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => DeliveryRequestResource::collection($requests),
        ]);
    }

    /**
     * Get the rider's currently active delivery.
     */
    public function active(Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        $delivery = $rider->deliveryRequests()
            ->with(['order', 'vendor'])
            ->active()
            ->first();

        if (! $delivery) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new DeliveryRequestResource($delivery),
        ]);
    }

    /**
     * Show a specific delivery request.
     */
    public function show(DeliveryRequest $deliveryRequest, Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        if ($deliveryRequest->rider_id !== $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $deliveryRequest->load(['order', 'vendor']);

        return response()->json([
            'success' => true,
            'data' => new DeliveryRequestResource($deliveryRequest),
        ]);
    }

    /**
     * Accept a delivery request.
     */
    public function accept(DeliveryRequest $deliveryRequest, Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        $accepted = $this->dispatchService->acceptDelivery($deliveryRequest, $rider);

        if (! $accepted) {
            return response()->json([
                'success' => false,
                'message' => 'This delivery has already been accepted.',
            ], 409);
        }

        event(new DeliveryStatusUpdated($deliveryRequest->fresh()));
        $deliveryRequest->load(['order', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Delivery accepted. Navigate to pickup location.',
            'data' => new DeliveryRequestResource($deliveryRequest->fresh()->load(['order', 'vendor'])),
        ]);
    }

    /**
     * Decline a delivery request.
     */
    public function decline(DeliveryRequest $deliveryRequest, Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $this->dispatchService->declineDelivery($deliveryRequest, $rider);

        return response()->json([
            'success' => true,
            'message' => 'Delivery declined.',
        ]);
    }

    /**
     * Confirm pickup of a delivery.
     */
    public function pickup(DeliveryRequest $deliveryRequest, Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        if ($deliveryRequest->rider_id !== $rider->id || $deliveryRequest->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm pickup for this delivery.',
            ], 403);
        }

        $deliveryRequest->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        event(new DeliveryStatusUpdated($deliveryRequest->fresh()));

        return response()->json([
            'success' => true,
            'message' => 'Pickup confirmed. Navigate to delivery location.',
            'data' => [
                'status' => 'picked_up',
                'picked_up_at' => $deliveryRequest->fresh()->picked_up_at->toISOString(),
            ],
        ]);
    }

    /**
     * Confirm delivery completion with PIN verification.
     */
    public function deliver(ConfirmDeliveryRequest $request, DeliveryRequest $deliveryRequest): JsonResponse
    {
        $rider = $request->user('rider');

        if ($deliveryRequest->rider_id !== $rider->id || ! in_array($deliveryRequest->status, ['picked_up', 'in_transit'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm delivery for this request.',
            ], 403);
        }

        // Verify PIN
        if ($request->delivery_pin !== $deliveryRequest->order->delivery_pin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid delivery PIN.',
            ], 422);
        }

        $deliveryRequest->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Update order status
        $deliveryRequest->order->markAsDelivered();

        // Credit rider earnings
        $earning = $this->balanceService->creditEarning($rider, $deliveryRequest);

        // Increment delivery count
        $rider->increment('total_deliveries');

        event(new DeliveryStatusUpdated($deliveryRequest->fresh()));

        return response()->json([
            'success' => true,
            'message' => 'Delivery confirmed! Earnings credited.',
            'data' => [
                'status' => 'delivered',
                'delivered_at' => $deliveryRequest->fresh()->delivered_at->toISOString(),
                'earning' => [
                    'amount' => (float) $earning->amount,
                    'status' => $earning->status,
                    'available_at' => $earning->available_at->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Cancel an active delivery with a reason.
     */
    public function cancel(CancelDeliveryRequest $request, DeliveryRequest $deliveryRequest): JsonResponse
    {
        $rider = $request->user('rider');

        if ($deliveryRequest->rider_id !== $rider->id || ! $deliveryRequest->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel this delivery.',
            ], 403);
        }

        $deliveryRequest->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason,
        ]);

        // Remove rider from order
        $deliveryRequest->order->update(['rider_id' => null]);

        event(new DeliveryStatusUpdated($deliveryRequest->fresh()));

        return response()->json([
            'success' => true,
            'message' => 'Delivery cancelled.',
        ]);
    }

    /**
     * Get the rider's delivery history with pagination.
     */
    public function history(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $perPage = min($request->input('per_page', 20), 100);

        $deliveries = $rider->deliveryRequests()
            ->whereIn('status', ['delivered', 'cancelled'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DeliveryHistoryResource::collection($deliveries),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }
}
