<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutRequestRequest;
use App\Http\Resources\PayoutRequestResource;
use App\Models\PayoutRequest;
use App\Services\PayoutService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PayoutService $payoutService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PayoutRequest::class);

        $query = PayoutRequest::query()->with(['user']);

        // Admin can see all, users see only their own
        if (! $request->user()->isAdmin() && ! $request->user()->isSuperAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $payouts = $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => PayoutRequestResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    public function store(StorePayoutRequestRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $payoutRequest = $this->payoutService->createPayoutRequest(
                $request->user(),
                $validated['amount'],
                $validated['payout_method'],
                $validated['mobile_money_number'] ?? null,
                $validated['mobile_money_provider'] ?? null,
                $validated['bank_name'] ?? null,
                $validated['account_number'] ?? null,
                $validated['account_name'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request created successfully.',
                'data' => new PayoutRequestResource($payoutRequest),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(PayoutRequest $payoutRequest): JsonResponse
    {
        $this->authorize('view', $payoutRequest);

        return response()->json([
            'success' => true,
            'data' => new PayoutRequestResource($payoutRequest->load('user')),
        ]);
    }

    public function approve(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        $this->authorize('approve', $payoutRequest);

        $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->payoutService->approve(
                $payoutRequest,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request approved successfully.',
                'data' => new PayoutRequestResource($payoutRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reject(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        $this->authorize('reject', $payoutRequest);

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->payoutService->reject(
                $payoutRequest,
                $request->user(),
                $request->input('rejection_reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request rejected.',
                'data' => new PayoutRequestResource($payoutRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(PayoutRequest $payoutRequest): JsonResponse
    {
        $this->authorize('cancel', $payoutRequest);

        if ($payoutRequest->status !== PayoutRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending payout requests can be cancelled.',
            ], 422);
        }

        $payoutRequest->update(['status' => PayoutRequest::STATUS_REJECTED]);

        return response()->json([
            'success' => true,
            'message' => 'Payout request cancelled.',
        ]);
    }
}
