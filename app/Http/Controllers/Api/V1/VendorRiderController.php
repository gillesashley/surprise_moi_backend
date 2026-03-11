<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\DispatchDeliveryRequest;
use App\Http\Resources\Api\Rider\V1\DeliveryRequestResource;
use App\Http\Resources\Api\Rider\V1\VendorRiderResource;
use App\Models\Order;
use App\Models\Rider;
use App\Models\VendorRider;
use App\Services\DeliveryDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorRiderController extends Controller
{
    public function __construct(protected DeliveryDispatchService $dispatchService) {}

    public function index(Request $request): JsonResponse
    {
        $vendorRiders = VendorRider::where('vendor_id', $request->user()->id)
            ->with('rider')
            ->get();

        return response()->json([
            'success' => true,
            'data' => VendorRiderResource::collection($vendorRiders),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'nickname' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $rider = Rider::where('phone', $request->phone)->where('status', 'approved')->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'No approved rider found with this phone number.',
            ], 404);
        }

        $existing = VendorRider::where('vendor_id', $request->user()->id)
            ->where('rider_id', $rider->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This rider is already in your preferred list.',
            ], 409);
        }

        if ($request->boolean('is_default')) {
            VendorRider::where('vendor_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $vendorRider = VendorRider::create([
            'vendor_id' => $request->user()->id,
            'rider_id' => $rider->id,
            'nickname' => $request->nickname,
            'is_default' => $request->boolean('is_default'),
        ]);

        $vendorRider->load('rider');

        return response()->json([
            'success' => true,
            'message' => 'Rider added to your preferred list.',
            'data' => new VendorRiderResource($vendorRider),
        ], 201);
    }

    public function destroy(VendorRider $vendorRider): JsonResponse
    {
        if ($vendorRider->vendor_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $vendorRider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rider removed from your preferred list.',
        ]);
    }

    public function dispatch(DispatchDeliveryRequest $request, Order $order): JsonResponse
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $deliveryRequest = $this->dispatchService->createDeliveryRequest(
            order: $order,
            vendorId: $request->user()->id,
            pickupAddress: $request->pickup_address,
            pickupLat: (float) $request->pickup_latitude,
            pickupLng: (float) $request->pickup_longitude,
            dropoffAddress: $request->dropoff_address,
            dropoffLat: (float) $request->dropoff_latitude,
            dropoffLng: (float) $request->dropoff_longitude,
            deliveryFee: (float) $request->delivery_fee,
            assignedRiderId: $request->rider_id,
        );

        $deliveryRequest->load(['order', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => $request->rider_id
                ? 'Delivery assigned to rider. Waiting for acceptance.'
                : 'Delivery request broadcast to nearby riders.',
            'data' => new DeliveryRequestResource($deliveryRequest),
        ], 201);
    }

    public function deliveryStatus(Order $order): JsonResponse
    {
        if ($order->vendor_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $deliveryRequest = $order->deliveryRequests()
            ->with(['rider', 'order'])
            ->latest()
            ->first();

        if (! $deliveryRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No delivery request found for this order.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new DeliveryRequestResource($deliveryRequest),
        ]);
    }
}
