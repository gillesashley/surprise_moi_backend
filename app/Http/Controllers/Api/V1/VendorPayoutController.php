<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorPayoutController extends Controller
{
    /**
     * Get vendor's payout request history.
     */
    public function index(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->with('processedBy:id,name')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'payouts' => $payouts,
        ]);
    }

    /**
     * Get vendor's available balance for payout.
     */
    public function balance(Request $request): JsonResponse
    {
        $vendor = $request->user();
        $balance = $vendor->vendorBalance;

        if (! $balance) {
            return response()->json([
                'success' => true,
                'balance' => [
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'currency' => config('app.currency', 'GHS'),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'balance' => [
                'available_balance' => $balance->available_balance,
                'pending_balance' => $balance->pending_balance,
                'total_earned' => $balance->total_earned,
                'total_withdrawn' => $balance->total_withdrawn,
                'currency' => $balance->currency,
            ],
        ]);
    }

    /**
     * Request payout from available balance.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:50', 'max:10000'],
            'payout_detail_id' => [
                'required',
                'exists:vendor_payout_details,id',
                function ($attribute, $value, $fail) use ($request) {
                    $detail = \App\Models\VendorPayoutDetail::find($value);
                    if (! $detail || $detail->vendor_id !== $request->user()->id) {
                        $fail('The selected payout details do not belong to you.');
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vendor = $request->user();
        $amount = $request->input('amount');
        $balance = $vendor->vendorBalance;

        if (! $balance || $balance->available_balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance.',
                'available_balance' => $balance?->available_balance ?? 0,
            ], 400);
        }

        $pendingCount = PayoutRequest::where('user_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending payout request. Please wait for it to be processed.',
            ], 400);
        }

        $payoutDetail = \App\Models\VendorPayoutDetail::find($request->input('payout_detail_id'));

        try {
            DB::beginTransaction();

            $payout = PayoutRequest::create([
                'user_id' => $vendor->id,
                'user_role' => $vendor->role,
                'amount' => $amount,
                'currency' => $balance->currency,
                'payout_method' => $payoutDetail->payout_method,
                'mobile_money_number' => $payoutDetail->payout_method === 'mobile_money' ? $payoutDetail->account_number : null,
                'mobile_money_provider' => $payoutDetail->provider,
                'bank_name' => $payoutDetail->bank_name,
                'account_number' => $payoutDetail->account_number,
                'account_name' => $payoutDetail->account_name,
                'payout_detail_id' => $payoutDetail->id,
                'status' => PayoutRequest::STATUS_PENDING,
                'notes' => $request->input('notes'),
            ]);

            $balance->decrement('available_balance', $amount);

            $vendor->vendorTransactions()->create([
                'type' => 'payout',
                'amount' => $amount,
                'currency' => $balance->currency,
                'status' => 'pending',
                'description' => "Payout request {$payout->request_number}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully. An admin will review it shortly.',
                'payout' => $payout->load('payoutDetail'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payout request. Please try again.',
            ], 500);
        }
    }

    /**
     * Get details of a specific payout request.
     */
    public function show(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        // Ensure vendor can only view their own payouts
        if ($payoutRequest->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $payoutRequest->load('processedBy:id,name');

        return response()->json([
            'success' => true,
            'payout' => $payoutRequest,
        ]);
    }
}
