<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VendorOnboardingPayment\InitiateOnboardingPaymentRequest;
use App\Http\Requests\Api\V1\VendorOnboardingPayment\ValidateCouponRequest;
use App\Models\VendorOnboardingPayment;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @tags Vendor Onboarding Payment
 */
class VendorOnboardingPaymentController extends Controller
{
    public function __construct(protected VendorOnboardingPaymentService $paymentService) {}

    /**
     * Get payment summary and fee information.
     *
     * Retrieve the onboarding fee based on vendor tier (Tier 1 for registered businesses, Tier 2 for individual vendors).
     */
    public function getPaymentSummary(Request $request): JsonResponse
    {
        $application = $request->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        if ($application->completed_step < 4) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete all registration steps before proceeding to payment.',
            ], 422);
        }

        if ($application->payment_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Payment has already been completed for this application.',
                'data' => [
                    'payment_completed' => true,
                    'payment_completed_at' => $application->payment_completed_at,
                ],
            ], 422);
        }

        $tier = $application->getVendorTier();
        $onboardingFee = $application->getOnboardingFee();

        return response()->json([
            'success' => true,
            'data' => [
                'application_id' => $application->id,
                'vendor_tier' => $tier,
                'vendor_type' => $tier === 1 ? 'Registered Business' : 'Individual Vendor',
                'onboarding_fee' => $onboardingFee,
                'currency' => 'GHS',
                'payment_required' => $application->payment_required,
                'payment_completed' => $application->payment_completed,
                'can_apply_coupon' => true,
            ],
        ]);
    }

    /**
     * Validate a coupon code.
     *
     * Check if a coupon code is valid and calculate the discount amount.
     */
    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $application = $request->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        $result = $this->paymentService->validateCoupon(
            $request->validated('coupon_code'),
            $application
        );

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'coupon_code' => $result['coupon']->code,
                'onboarding_fee' => $result['onboarding_fee'],
                'discount_amount' => $result['discount_amount'],
                'final_amount' => $result['final_amount'],
                'currency' => 'GHS',
            ],
        ]);
    }

    /**
     * Initialize vendor onboarding payment.
     *
     * Creates a payment transaction and returns a Paystack authorization URL to complete payment in browser.
     */
    public function initiate(InitiateOnboardingPaymentRequest $request): JsonResponse
    {
        // Rate limiting: max 5 payment initiations per minute per user
        $key = 'vendor-onboarding-payment-initiate:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many payment attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $application = $request->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        if ($application->completed_step < 4) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete all registration steps before proceeding to payment.',
            ], 422);
        }

        if ($application->payment_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Payment has already been completed for this application.',
            ], 422);
        }

        $result = $this->paymentService->initializePayment(
            $application,
            $request->validated('coupon_code'),
            $request->validated('callback_url')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized successfully.',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
                'reference' => $result['payment']->reference,
                'amount' => (float) $result['payment']->amount,
                'currency' => 'GHS',
            ],
        ], 201);
    }

    /**
     * Verify a payment transaction.
     *
     * Verify the status of a vendor onboarding payment after user returns from payment page.
     */
    public function verify(Request $request): JsonResponse
    {
        // Rate limiting: max 10 verification attempts per minute per user
        $key = 'vendor-onboarding-payment-verify:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many verification attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $request->validate([
            'reference' => 'required|string|starts_with:VOP-',
        ]);

        $application = $request->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        $payment = VendorOnboardingPayment::where('reference', $request->reference)
            ->where('user_id', $request->user()->id)
            ->where('vendor_application_id', $application->id)
            ->first();

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found.',
            ], 404);
        }

        if ($payment->isSuccessful()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already verified successfully.',
                'data' => [
                    'payment_status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at,
                    'can_submit_application' => true,
                ],
            ]);
        }

        $result = $this->paymentService->verifyPayment($payment);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => [
                    'payment_status' => $payment->status,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully.',
            'data' => [
                'payment_status' => 'success',
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
                'can_submit_application' => true,
            ],
        ]);
    }

    /**
     * Get payment status for current application.
     *
     * Check the payment status of the current vendor application.
     */
    public function status(Request $request): JsonResponse
    {
        $application = $request->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        $latestPayment = $application->latestOnboardingPayment;

        return response()->json([
            'success' => true,
            'data' => [
                'payment_required' => $application->payment_required,
                'payment_completed' => $application->payment_completed,
                'payment_completed_at' => $application->payment_completed_at,
                'onboarding_fee' => $application->onboarding_fee,
                'discount_amount' => $application->discount_amount,
                'final_amount' => $application->final_amount,
                'latest_payment' => $latestPayment ? [
                    'reference' => $latestPayment->reference,
                    'status' => $latestPayment->status,
                    'amount' => $latestPayment->amount,
                    'authorization_url' => $latestPayment->authorization_url,
                    'paid_at' => $latestPayment->paid_at,
                ] : null,
                'can_submit_application' => $application->canSubmit(),
            ],
        ]);
    }

    /**
     * Handle Paystack payment callback.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (! $reference) {
            return $this->redirectToApp('failed', null, 'Payment reference not provided');
        }

        $payment = VendorOnboardingPayment::where('reference', $reference)->first();

        if (! $payment) {
            return $this->redirectToApp('failed', $reference, 'Payment record not found');
        }

        if ($payment->isSuccessful()) {
            Log::info('Vendor onboarding payment already verified', ['reference' => $reference]);

            return $this->redirectToApp('success', $reference);
        }

        $result = $this->paymentService->verifyPayment($payment);

        if (! $result['success']) {
            Log::warning('Vendor onboarding payment verification failed', [
                'reference' => $reference,
                'message' => $result['message'],
            ]);

            return $this->redirectToApp('failed', $reference, $result['message']);
        }

        Log::info('Vendor onboarding payment verified successfully', ['reference' => $reference]);

        return $this->redirectToApp('success', $reference);
    }

    /**
     * Redirect to mobile app via deep link.
     */
    private function redirectToApp(
        string $status,
        ?string $reference = null,
        ?string $message = null
    ) {
        $params = ['status' => $status, 'type' => 'vendor'];

        if ($reference) {
            $params['reference'] = $reference;
        }

        if ($message && $status !== 'success') {
            $params['message'] = $message;
        }

        $deepLinkUrl = 'surprisemoi://payment-callback?'.http_build_query($params);

        Log::info('Redirecting to mobile app', [
            'deep_link' => $deepLinkUrl,
            'status' => $status,
            'type' => 'vendor',
        ]);

        return redirect($deepLinkUrl);
    }

    /**
     * Handle Paystack webhook notifications.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature', '');

        if (! $this->paymentService->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event !== 'charge.success') {
            return response()->json(['success' => true]);
        }

        $reference = $data['reference'] ?? null;

        if (! $reference || ! str_starts_with($reference, 'VOP-')) {
            return response()->json(['success' => true]);
        }

        $payment = VendorOnboardingPayment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Webhook received for unknown payment reference', [
                'reference' => $reference,
            ]);

            return response()->json(['success' => true]);
        }

        if ($payment->isSuccessful()) {
            return response()->json(['success' => true]);
        }

        $this->paymentService->verifyPayment($payment);

        return response()->json(['success' => true]);
    }
}
