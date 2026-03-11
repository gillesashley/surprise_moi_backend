<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\UpdateLocationRequest;
use App\Http\Resources\Api\Rider\V1\DeliveryRequestResource;
use App\Services\RiderBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected RiderBalanceService $balanceService) {}

    /**
     * Get the rider's dashboard summary.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $balance = $this->balanceService->getBalanceSummary($rider);

        $activeDelivery = $rider->deliveryRequests()
            ->with(['order', 'vendor'])
            ->active()
            ->first();

        $todayEarnings = (float) $rider->earnings()
            ->whereDate('created_at', today())
            ->sum('amount');

        $todayDeliveries = $rider->deliveryRequests()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'is_online' => (bool) $rider->is_online,
                'today_earnings' => $todayEarnings,
                'today_deliveries' => $todayDeliveries,
                'total_earnings' => $balance['total_earned'],
                'total_deliveries' => (int) $rider->total_deliveries,
                'average_rating' => (float) $rider->average_rating,
                'available_balance' => $balance['available'],
                'pending_balance' => $balance['pending'],
                'active_delivery' => $activeDelivery ? new DeliveryRequestResource($activeDelivery) : null,
            ],
        ]);
    }

    /**
     * Toggle the rider's online/offline status.
     */
    public function toggleOnline(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $rider->update(['is_online' => ! $rider->is_online]);

        return response()->json([
            'success' => true,
            'message' => $rider->fresh()->is_online ? 'You are now online.' : 'You are now offline.',
            'data' => ['is_online' => (bool) $rider->fresh()->is_online],
        ]);
    }

    /**
     * Update the rider's current GPS location.
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $request->user('rider')->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'location_updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update the rider's push notification device token.
     */
    public function updateDeviceToken(Request $request): JsonResponse
    {
        $request->validate(['device_token' => 'required|string']);

        $request->user('rider')->update([
            'device_token' => $request->device_token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device token updated.',
        ]);
    }
}
