<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorBalanceResource;
use App\Models\User;
use App\Models\VendorBalance;
use App\Services\VendorBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVendorBalanceController extends Controller
{
    public function __construct(protected VendorBalanceService $balanceService) {}

    /**
     * Get all vendor balances.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $balances = VendorBalance::query()
            ->with('vendor')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('vendor', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('total_earned', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'balances' => VendorBalanceResource::collection($balances),
                'pagination' => [
                    'current_page' => $balances->currentPage(),
                    'last_page' => $balances->lastPage(),
                    'per_page' => $balances->perPage(),
                    'total' => $balances->total(),
                ],
            ],
        ]);
    }

    /**
     * Get specific vendor's balance.
     */
    public function show(int $vendorId): JsonResponse
    {
        $vendor = User::findOrFail($vendorId);

        if (! $vendor->isVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a vendor.',
            ], 422);
        }

        $balance = $this->balanceService->getOrCreateBalance($vendorId);
        $balance->load('vendor');

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => new VendorBalanceResource($balance),
            ],
        ]);
    }

    /**
     * Process payout to vendor.
     */
    public function payout(Request $request, int $vendorId): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $vendor = User::findOrFail($vendorId);

        if (! $vendor->isVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a vendor.',
            ], 422);
        }

        try {
            $transaction = $this->balanceService->processPayout(
                $vendorId,
                $request->input('amount'),
                $request->input('description')
            );

            $balance = $this->balanceService->getOrCreateBalance($vendorId);

            return response()->json([
                'success' => true,
                'message' => 'Payout processed successfully.',
                'data' => [
                    'balance' => new VendorBalanceResource($balance),
                    'transaction' => $transaction,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
