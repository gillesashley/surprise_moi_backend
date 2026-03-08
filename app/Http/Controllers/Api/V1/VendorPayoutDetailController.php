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
     * Save new payout details.
     */
    public function store(StoreVendorPayoutDetailRequest $request): JsonResponse
    {
        $vendor = $request->user();
        $payoutMethod = $request->input('payout_method');

        if ($payoutMethod === VendorPayoutDetail::METHOD_BANK_TRANSFER) {
            return $this->storeBankTransfer($request, $vendor);
        }

        return $this->storeMobileMoney($request, $vendor);
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

        // Deactivate on Paystack if it has a recipient code
        if ($vendorPayoutDetail->paystack_recipient_code) {
            $this->paystackService->deleteTransferRecipient($vendorPayoutDetail->paystack_recipient_code);
        }

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

    /**
     * Fetch available mobile money providers from Paystack.
     */
    public function mobileMoneyProviders(): JsonResponse
    {
        $result = $this->paystackService->listMobileMoneyProviders('GHS');

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'providers' => $result['data'],
        ]);
    }

    /**
     * Save mobile money payout details with Paystack verification.
     */
    private function storeMobileMoney(StoreVendorPayoutDetailRequest $request, $vendor): JsonResponse
    {
        $accountName = $request->input('account_name', $vendor->name);

        $recipientResult = $this->paystackService->createTransferRecipient(
            type: 'mobile_money',
            name: $accountName,
            accountNumber: $request->input('account_number'),
            bankCode: $request->input('bank_code'),
            currency: 'GHS'
        );

        if (! $recipientResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $recipientResult['message'] ?? 'Could not verify mobile money account. Please check your details.',
            ], 422);
        }

        $bankName = $recipientResult['data']['details']['bank_name']
            ?? $this->resolveMoMoProviderName($request->input('bank_code'));

        // Unset existing defaults
        $vendor->payoutDetails()->update(['is_default' => false]);

        $detail = VendorPayoutDetail::create([
            'vendor_id' => $vendor->id,
            'payout_method' => VendorPayoutDetail::METHOD_MOBILE_MONEY,
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
            'message' => 'Mobile money payout details saved and verified successfully.',
            'payout_detail' => $detail,
        ], 201);
    }

    /**
     * Save bank transfer payout details with Paystack verification.
     */
    private function storeBankTransfer(StoreVendorPayoutDetailRequest $request, $vendor): JsonResponse
    {
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

        $recipientResult = $this->paystackService->createTransferRecipient(
            type: 'ghipss',
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

        $bankName = $recipientResult['data']['details']['bank_name']
            ?? $request->input('bank_code');

        // Unset existing defaults
        $vendor->payoutDetails()->update(['is_default' => false]);

        $detail = VendorPayoutDetail::create([
            'vendor_id' => $vendor->id,
            'payout_method' => VendorPayoutDetail::METHOD_BANK_TRANSFER,
            'account_name' => $accountName,
            'account_number' => $request->input('account_number'),
            'bank_code' => $request->input('bank_code'),
            'bank_name' => $bankName,
            'provider' => null,
            'paystack_recipient_code' => $recipientResult['data']['recipient_code'],
            'is_verified' => true,
            'is_default' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank payout details saved and verified successfully.',
            'payout_detail' => $detail,
        ], 201);
    }

    private function resolveMoMoProviderName(string $bankCode): string
    {
        $providers = [
            'MTN' => 'MTN Mobile Money',
            'mtn' => 'MTN Mobile Money',
            'mtn_gh' => 'MTN Mobile Money',
            'VOD' => 'Vodafone Cash',
            'vodafone' => 'Vodafone Cash',
            'vodafone_gh' => 'Vodafone Cash',
            'ATL' => 'AirtelTigo Money',
            'airteltigo' => 'AirtelTigo Money',
            'airteltigo_gh' => 'AirtelTigo Money',
        ];

        return $providers[$bankCode] ?? $bankCode;
    }
}
