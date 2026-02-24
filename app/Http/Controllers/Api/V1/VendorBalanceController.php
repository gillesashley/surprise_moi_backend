<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorBalanceResource;
use App\Http\Resources\VendorTransactionResource;
use App\Models\VendorTransaction;
use App\Services\VendorBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorBalanceController extends Controller
{
    public function __construct(protected VendorBalanceService $balanceService) {}

    /**
     * Get vendor's balance (for authenticated vendor).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'Only vendors can access balance information.',
            ], 403);
        }

        $balance = $this->balanceService->getOrCreateBalance($user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => new VendorBalanceResource($balance),
            ],
        ]);
    }

    /**
     * Get vendor's transaction history.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'Only vendors can access transaction history.',
            ], 403);
        }

        $perPage = $request->input('per_page', 15);

        $transactions = VendorTransaction::query()
            ->forVendor($user->id)
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => VendorTransactionResource::collection($transactions),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }
}
