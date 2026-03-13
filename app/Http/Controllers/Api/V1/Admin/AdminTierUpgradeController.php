<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TierUpgrade\RejectTierUpgradeRequest;
use App\Http\Resources\TierUpgradeRequestResource;
use App\Models\TierUpgradeRequest;
use App\Notifications\TierUpgradeApprovedNotification;
use App\Notifications\TierUpgradeRejectedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTierUpgradeController extends Controller
{
    /**
     * GET /api/v1/admin/vendor/upgrade-tier
     */
    public function index(Request $request): JsonResponse
    {
        $query = TierUpgradeRequest::query()->with('vendor');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $requests = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => TierUpgradeRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/vendor/upgrade-tier/{tierUpgradeRequest}
     */
    public function show(TierUpgradeRequest $tierUpgradeRequest): JsonResponse
    {
        $tierUpgradeRequest->load('vendor');

        return response()->json([
            'success' => true,
            'data' => new TierUpgradeRequestResource($tierUpgradeRequest),
        ]);
    }

    /**
     * POST /api/v1/admin/vendor/upgrade-tier/{tierUpgradeRequest}/approve
     */
    public function approve(Request $request, TierUpgradeRequest $tierUpgradeRequest): JsonResponse
    {
        if (! $tierUpgradeRequest->canBeReviewed()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be approved in its current status.',
            ], 422);
        }

        DB::transaction(function () use ($request, $tierUpgradeRequest) {
            $tierUpgradeRequest->update([
                'status' => TierUpgradeRequest::STATUS_APPROVED,
                'admin_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $tierUpgradeRequest->vendor->update([
                'vendor_tier' => 1,
            ]);
        });

        $tierUpgradeRequest->vendor->notify(new TierUpgradeApprovedNotification($tierUpgradeRequest));

        return response()->json([
            'success' => true,
            'message' => 'Upgrade request approved. Vendor is now Tier 1.',
            'data' => new TierUpgradeRequestResource($tierUpgradeRequest->fresh()->load('vendor')),
        ]);
    }

    /**
     * POST /api/v1/admin/vendor/upgrade-tier/{tierUpgradeRequest}/reject
     */
    public function reject(RejectTierUpgradeRequest $request, TierUpgradeRequest $tierUpgradeRequest): JsonResponse
    {
        if (! $tierUpgradeRequest->canBeReviewed()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be rejected in its current status.',
            ], 422);
        }

        $tierUpgradeRequest->update([
            'status' => TierUpgradeRequest::STATUS_REJECTED,
            'admin_id' => $request->user()->id,
            'admin_notes' => $request->validated('admin_notes'),
            'reviewed_at' => now(),
        ]);

        $tierUpgradeRequest->vendor->notify(new TierUpgradeRejectedNotification($tierUpgradeRequest));

        return response()->json([
            'success' => true,
            'message' => 'Upgrade request rejected.',
            'data' => new TierUpgradeRequestResource($tierUpgradeRequest->fresh()),
        ]);
    }
}
