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
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_method' => ['required', 'in:mobile_money,bank_transfer'],
            'mobile_money_number' => ['required_if:payout_method,mobile_money', 'nullable', 'string'],
            'mobile_money_provider' => ['required_if:payout_method,mobile_money', 'nullable', 'in:mtn,vodafone,airteltigo'],
            'bank_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string'],
            'account_number' => ['required_if:payout_method,bank_transfer', 'nullable', 'string'],
            'account_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string'],
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

        // Check if vendor has sufficient available balance
        $balance = $vendor->vendorBalance;

        if (! $balance || $balance->available_balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance.',
                'available_balance' => $balance?->available_balance ?? 0,
            ], 400);
        }

        // Check for pending payout requests
        $pendingCount = PayoutRequest::where('user_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending payout request. Please wait for it to be processed.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Create payout request
            $payout = PayoutRequest::create([
                'user_id' => $vendor->id,
                'user_role' => $vendor->role,
                'amount' => $amount,
                'currency' => $balance->currency,
                'payout_method' => $request->input('payout_method'),
                'mobile_money_number' => $request->input('mobile_money_number'),
                'mobile_money_provider' => $request->input('mobile_money_provider'),
                'bank_name' => $request->input('bank_name'),
                'account_number' => $request->input('account_number'),
                'account_name' => $request->input('account_name'),
                'status' => PayoutRequest::STATUS_PENDING,
                'notes' => $request->input('notes'),
            ]);

            // Deduct from available balance (reserve it)
            $balance->available_balance -= $amount;
            $balance->save();

            // Log transaction
            $vendor->vendorTransactions()->create([
                'type' => 'payout',
                'amount' => -$amount,
                'currency' => $balance->currency,
                'status' => 'pending',
                'description' => "Payout request {$payout->request_number}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully. An admin will review it shortly.',
                'payout' => $payout,
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
