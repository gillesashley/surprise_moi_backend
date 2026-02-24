<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminPayoutController extends Controller
{
    /**
     * Get all payout requests for admin review.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status'); // pending, approved, rejected, paid
        $search = $request->input('search');

        $query = PayoutRequest::with(['user:id,name,email,phone', 'processedBy:id,name'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $payouts = $query->paginate(15);

        // Get statistics
        $stats = [
            'total_pending' => PayoutRequest::where('status', 'pending')->count(),
            'total_approved' => PayoutRequest::where('status', 'approved')->count(),
            'total_paid' => PayoutRequest::where('status', 'paid')->count(),
            'total_rejected' => PayoutRequest::where('status', 'rejected')->count(),
            'pending_amount' => PayoutRequest::where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'payouts' => $payouts,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get details of a specific payout request.
     */
    public function show(PayoutRequest $payoutRequest): JsonResponse
    {
        $payoutRequest->load(['user:id,name,email,phone,role', 'processedBy:id,name']);

        // Get vendor's balance info
        $vendorBalance = $payoutRequest->user->vendorBalance;

        return response()->json([
            'success' => true,
            'payout' => $payoutRequest,
            'vendor_balance' => [
                'available_balance' => $vendorBalance->available_balance ?? 0,
                'pending_balance' => $vendorBalance->pending_balance ?? 0,
                'total_earned' => $vendorBalance->total_earned ?? 0,
            ],
        ]);
    }

    /**
     * Approve a payout request.
     */
    public function approve(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if ($payoutRequest->status !== PayoutRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending payout requests can be approved.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_APPROVED,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'notes' => $request->input('admin_notes'),
            ]);

            // Note: Balance was already deducted when request was created
            // Status approved means admin has reviewed and approved the payout
            // Next step is to actually send money and mark as 'paid'

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout request approved successfully.',
                'payout' => $payoutRequest->fresh(['user', 'processedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payout request.',
            ], 500);
        }
    }

    /**
     * Reject a payout request.
     */
    public function reject(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if ($payoutRequest->status !== PayoutRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending payout requests can be rejected.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vendor = $payoutRequest->user;
            $balance = $vendor->vendorBalance;

            // Reject the request
            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_REJECTED,
                'rejection_reason' => $request->input('rejection_reason'),
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'rejected_at' => now(),
            ]);

            // Return money to vendor's available balance
            $balance->available_balance += $payoutRequest->amount;
            $balance->save();

            // Log transaction
            $vendor->vendorTransactions()->create([
                'type' => 'refund',
                'amount' => $payoutRequest->amount,
                'currency' => $balance->currency,
                'status' => 'completed',
                'description' => "Payout {$payoutRequest->request_number} rejected: {$request->input('rejection_reason')}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout request rejected successfully.',
                'payout' => $payoutRequest->fresh(['user', 'processedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject payout request.',
            ], 500);
        }
    }

    /**
     * Mark an approved payout as paid (after sending money via mobile money).
     */
    public function markAsPaid(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if ($payoutRequest->status !== PayoutRequest::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Only approved payout requests can be marked as paid.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_reference' => ['required', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vendor = $payoutRequest->user;
            $balance = $vendor->vendorBalance;

            // Mark as paid
            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_PAID,
                'payment_reference' => $request->input('payment_reference'),
                'paid_at' => now(),
                'notes' => $request->input('admin_notes'),
            ]);

            // Update vendor's total withdrawn
            $balance->total_withdrawn += $payoutRequest->amount;
            $balance->save();

            // Log transaction
            $vendor->vendorTransactions()->create([
                'type' => 'payout',
                'amount' => -$payoutRequest->amount,
                'currency' => $balance->currency,
                'status' => 'completed',
                'description' => "Payout {$payoutRequest->request_number} completed. Ref: {$request->input('payment_reference')}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout marked as paid successfully.',
                'payout' => $payoutRequest->fresh(['user', 'processedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payout as paid.',
            ], 500);
        }
    }
}
