<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\WithdrawalRequest;
use App\Http\Resources\Api\Rider\V1\RiderEarningResource;
use App\Http\Resources\Api\Rider\V1\WithdrawalRequestResource;
use App\Services\RiderBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningController extends Controller
{
    public function __construct(protected RiderBalanceService $balanceService) {}

    public function index(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $summary = $this->balanceService->getBalanceSummary($rider);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $perPage = min($request->input('per_page', 20), 100);

        $earnings = $rider->earnings()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => RiderEarningResource::collection($earnings),
            'meta' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
            ],
        ]);
    }

    public function withdraw(WithdrawalRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        $withdrawal = $this->balanceService->processWithdrawal(
            $rider,
            (float) $request->amount,
            $request->mobile_money_provider,
            $request->mobile_money_number,
        );

        if (! $withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted. Processing takes 1-24 hours.',
            'data' => new WithdrawalRequestResource($withdrawal),
        ], 201);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $rider = $request->user('rider');
        $perPage = min($request->input('per_page', 20), 100);

        $withdrawals = $rider->withdrawalRequests()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WithdrawalRequestResource::collection($withdrawals),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }
}
