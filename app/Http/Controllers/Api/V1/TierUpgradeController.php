<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TierUpgrade\SubmitTierUpgradeDocumentRequest;
use App\Http\Requests\Api\V1\TierUpgrade\VerifyTierUpgradePaymentRequest;
use App\Http\Resources\TierUpgradeRequestResource;
use App\Models\TierUpgradeRequest;
use App\Models\User;
use App\Notifications\TierUpgradeSubmittedNotification;
use App\Services\TierUpgradePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class TierUpgradeController extends Controller
{
    public function __construct(protected TierUpgradePaymentService $paymentService) {}

    /**
     * Ensure the vendor is Tier 2. Returns a 403 response if not.
     */
    protected function ensureTier2(User $vendor): ?JsonResponse
    {
        if ($vendor->vendor_tier !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Only Tier 2 vendors can upgrade.',
            ], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/vendor/upgrade-tier/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $existingRequest = $vendor->activeTierUpgradeRequest();

        return response()->json([
            'success' => true,
            'data' => [
                'upgrade_fee' => $this->paymentService->getUpgradeFee(),
                'currency' => config('services.paystack.currency', 'GHS'),
                'current_tier' => $vendor->vendor_tier,
                'existing_request' => $existingRequest
                    ? new TierUpgradeRequestResource($existingRequest)
                    : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/upgrade-tier/payment/initiate
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $existingRequest = $vendor->activeTierUpgradeRequest();
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active upgrade request.',
            ], 409);
        }

        $result = $this->paymentService->initializePayment($vendor);

        if (! $result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result, 201);
    }

    /**
     * POST /api/v1/vendor/upgrade-tier/payment/verify
     */
    public function verifyPayment(VerifyTierUpgradePaymentRequest $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $reference = $request->validated('reference');

        $upgradeRequest = TierUpgradeRequest::where('vendor_id', $vendor->id)
            ->where('payment_reference', $reference)
            ->first();

        if (! $upgradeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Upgrade request not found for this reference.',
            ], 404);
        }

        $result = $this->paymentService->verifyPayment($upgradeRequest);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /api/v1/vendor/upgrade-tier/payment/callback
     */
    public function callback(Request $request): JsonResponse
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (! $reference || ! str_starts_with($reference, 'TUP-')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment reference.',
            ], 400);
        }

        $upgradeRequest = TierUpgradeRequest::where('payment_reference', $reference)->first();

        if (! $upgradeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Upgrade request not found.',
            ], 404);
        }

        if ($upgradeRequest->isPendingPayment()) {
            $result = $this->paymentService->verifyPayment($upgradeRequest);

            return response()->json($result, $result['success'] ? 200 : 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment already verified.',
            'data' => new TierUpgradeRequestResource($upgradeRequest),
        ]);
    }

    /**
     * POST /api/v1/vendor/upgrade-tier/payment/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature', '');

        if (! $this->paymentService->validateWebhookSignature($payload, $signature)) {
            Log::warning('Tier upgrade webhook: invalid signature');

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        $this->paymentService->handleWebhook($event);

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * POST /api/v1/vendor/upgrade-tier/submit-document
     */
    public function submitDocument(SubmitTierUpgradeDocumentRequest $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $upgradeRequest = $vendor->activeTierUpgradeRequest();

        if (! $upgradeRequest || ! $upgradeRequest->canSubmitDocument()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible upgrade request found for document submission.',
            ], 422);
        }

        // Delete old file on resubmission
        if ($upgradeRequest->business_certificate_document) {
            Storage::delete($upgradeRequest->business_certificate_document);
        }

        $path = $request->file('business_certificate_document')
            ->store("tier-upgrades/business-certificates/{$vendor->id}");

        $upgradeRequest->update([
            'status' => TierUpgradeRequest::STATUS_PENDING_REVIEW,
            'business_certificate_document' => $path,
            'admin_id' => null,
            'admin_notes' => null,
            'reviewed_at' => null,
        ]);

        // Notify admins
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        Notification::send($admins, new TierUpgradeSubmittedNotification($upgradeRequest));

        return response()->json([
            'success' => true,
            'message' => 'Document submitted successfully. Your request is now under review.',
            'data' => new TierUpgradeRequestResource($upgradeRequest->fresh()),
        ]);
    }

    /**
     * GET /api/v1/vendor/upgrade-tier/status
     */
    public function status(Request $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $upgradeRequest = $vendor->activeTierUpgradeRequest();

        if (! $upgradeRequest) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new TierUpgradeRequestResource($upgradeRequest),
        ]);
    }

    /**
     * DELETE /api/v1/vendor/upgrade-tier/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $vendor = $request->user();

        if ($denied = $this->ensureTier2($vendor)) {
            return $denied;
        }

        $upgradeRequest = $vendor->activeTierUpgradeRequest();

        if (! $upgradeRequest || ! $upgradeRequest->isPendingPayment()) {
            return response()->json([
                'success' => false,
                'message' => 'No cancellable upgrade request found.',
            ], 422);
        }

        $upgradeRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Upgrade request cancelled.',
        ]);
    }
}
