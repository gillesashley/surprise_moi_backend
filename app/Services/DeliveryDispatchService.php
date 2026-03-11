<?php

namespace App\Services;

use App\Jobs\BroadcastDeliveryRequest;
use App\Models\DeliveryRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Notifications\NewDeliveryRequestNotification;

class DeliveryDispatchService
{
    private const BROADCAST_RADII = [5, 10, 20];

    private const BROADCAST_TIMEOUT_SECONDS = 30;

    private const MAX_BROADCAST_ATTEMPTS = 3;

    /**
     * Create a delivery request and start dispatch.
     */
    public function createDeliveryRequest(
        Order $order,
        int $vendorId,
        string $pickupAddress,
        float $pickupLat,
        float $pickupLng,
        string $dropoffAddress,
        float $dropoffLat,
        float $dropoffLng,
        float $deliveryFee,
        ?int $assignedRiderId = null,
    ): DeliveryRequest {
        $deliveryRequest = DeliveryRequest::create([
            'order_id' => $order->id,
            'vendor_id' => $vendorId,
            'assigned_rider_id' => $assignedRiderId,
            'status' => $assignedRiderId ? 'assigned' : 'broadcasting',
            'pickup_address' => $pickupAddress,
            'pickup_latitude' => $pickupLat,
            'pickup_longitude' => $pickupLng,
            'dropoff_address' => $dropoffAddress,
            'dropoff_latitude' => $dropoffLat,
            'dropoff_longitude' => $dropoffLng,
            'delivery_fee' => $deliveryFee,
            'distance_km' => $this->calculateDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng),
            'broadcast_radius_km' => self::BROADCAST_RADII[0],
            'expires_at' => now()->addSeconds(self::BROADCAST_TIMEOUT_SECONDS),
        ]);

        if ($assignedRiderId) {
            $this->notifyAssignedRider($deliveryRequest);
        } else {
            BroadcastDeliveryRequest::dispatch($deliveryRequest);
        }

        return $deliveryRequest;
    }

    /**
     * Broadcast delivery request to nearby riders.
     */
    public function broadcastToNearbyRiders(DeliveryRequest $deliveryRequest): void
    {
        if ($deliveryRequest->broadcast_attempts >= self::MAX_BROADCAST_ATTEMPTS) {
            $deliveryRequest->update(['status' => 'expired']);

            return;
        }

        $radiusIndex = min($deliveryRequest->broadcast_attempts, count(self::BROADCAST_RADII) - 1);
        $radius = self::BROADCAST_RADII[$radiusIndex];

        $riders = Rider::query()
            ->approved()
            ->online()
            ->nearby($deliveryRequest->pickup_latitude, $deliveryRequest->pickup_longitude, $radius)
            ->whereDoesntHave('deliveryRequests', fn ($q) => $q->active())
            ->get();

        foreach ($riders as $rider) {
            $rider->notify(new NewDeliveryRequestNotification($deliveryRequest));
        }

        $deliveryRequest->update([
            'broadcast_attempts' => $deliveryRequest->broadcast_attempts + 1,
            'broadcast_radius_km' => $radius,
            'expires_at' => now()->addSeconds(self::BROADCAST_TIMEOUT_SECONDS),
        ]);
    }

    /**
     * Accept a delivery request for a rider.
     */
    public function acceptDelivery(DeliveryRequest $deliveryRequest, Rider $rider): bool
    {
        if (! in_array($deliveryRequest->status, ['broadcasting', 'assigned'])) {
            return false;
        }

        if ($deliveryRequest->status === 'assigned' && $deliveryRequest->assigned_rider_id !== $rider->id) {
            return false;
        }

        $deliveryRequest->update([
            'rider_id' => $rider->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $deliveryRequest->order->update([
            'rider_id' => $rider->id,
        ]);

        return true;
    }

    /**
     * Decline a delivery request.
     */
    public function declineDelivery(DeliveryRequest $deliveryRequest, Rider $rider): void
    {
        if ($deliveryRequest->status === 'assigned' && $deliveryRequest->assigned_rider_id === $rider->id) {
            $deliveryRequest->update(['assigned_rider_id' => null, 'status' => 'broadcasting']);
            BroadcastDeliveryRequest::dispatch($deliveryRequest);
        }
    }

    /**
     * Notify the assigned rider about a delivery request.
     */
    private function notifyAssignedRider(DeliveryRequest $deliveryRequest): void
    {
        $rider = Rider::find($deliveryRequest->assigned_rider_id);
        if ($rider) {
            $rider->notify(new NewDeliveryRequestNotification($deliveryRequest));
        }
    }

    /**
     * Calculate distance between two points using Haversine formula.
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
