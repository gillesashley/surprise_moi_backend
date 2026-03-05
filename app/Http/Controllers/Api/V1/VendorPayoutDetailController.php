<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVendorPayoutDetailRequest;
use App\Models\VendorPayoutDetail;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPayoutDetailController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService
    ) {}

    /**
     * List vendor's saved payout details.
     */
    public function index(Request $request): JsonResponse
    {
        $details = $request->user()->payoutDetails()->latest()->get();

        return response()->json([
            'success' => true,
            'payout_details' => $details,
        ]);
    }

    /**
     * Save new payout details with Paystack verification.
     */
    public function store(StoreVendorPayoutDetailRequest $request): JsonResponse
    {
        $vendor = $request->user();

        // Step 1: Resolve account number via Paystack
        $resolveResult = $this->paystackService->resolveAccountNumber(
            $request->input('account_number'),
            $request->input('bank_code')
        );

        if (! $resolveResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $resolveResult['message'] ?? 'Could not verify account. Please check your details.',
            ], 422);
        }

        $accountName = $resolveResult['data']['account_name'];

        // Step 2: Determine recipient type for Paystack
        $recipientType = $request->input('payout_method') === VendorPayoutDetail::METHOD_MOBILE_MONEY
            ? 'mobile_money'
            : 'ghipss';

        // Step 3: Create Paystack transfer recipient
        $recipientResult = $this->paystackService->createTransferRecipient(
            type: $recipientType,
            name: $accountName,
            accountNumber: $request->input('account_number'),
            bankCode: $request->input('bank_code'),
            currency: 'GHS'
        );

        if (! $recipientResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $recipientResult['message'] ?? 'Could not register payout details. Please try again.',
            ], 422);
        }

        // Step 4: Get bank name from Paystack data
        $bankName = $recipientResult['data']['details']['bank_name']
            ?? $request->input('bank_code');

        // Step 5: Unset existing defaults
        $vendor->payoutDetails()->update(['is_default' => false]);

        // Step 6: Save payout details
        $detail = VendorPayoutDetail::create([
            'vendor_id' => $vendor->id,
            'payout_method' => $request->input('payout_method'),
            'account_name' => $accountName,
            'account_number' => $request->input('account_number'),
            'bank_code' => $request->input('bank_code'),
            'bank_name' => $bankName,
            'provider' => $request->input('provider'),
            'paystack_recipient_code' => $recipientResult['data']['recipient_code'],
            'is_verified' => true,
            'is_default' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout details saved and verified successfully.',
            'payout_detail' => $detail,
        ], 201);
    }

    /**
     * Delete saved payout details.
     */
    public function destroy(Request $request, VendorPayoutDetail $vendorPayoutDetail): JsonResponse
    {
        if ($vendorPayoutDetail->vendor_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Deactivate on Paystack
        $this->paystackService->deleteTransferRecipient($vendorPayoutDetail->paystack_recipient_code);

        $wasDefault = $vendorPayoutDetail->is_default;
        $vendorPayoutDetail->delete();

        // If deleted was default, set another as default
        if ($wasDefault) {
            $request->user()->payoutDetails()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payout details removed.',
        ]);
    }

    /**
     * Fetch available banks from Paystack.
     */
    public function banks(): JsonResponse
    {
        $result = $this->paystackService->listBanks('GHS');

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'banks' => $result['data'],
        ]);
    }
}
