<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InitiatePaymentRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PaymentController extends Controller
{
    public function __construct(protected PaystackService $paystackService) {}

    /**
     * Get user's payment history.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->where('user_id', $request->user()->id)
            ->with('order')
            ->latest()
            ->paginate($request->input('per_page', 15));

        return PaymentResource::collection($payments);
    }

    /**
     * Show a specific payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        // Ensure user owns this payment
        if ($payment->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'payment' => new PaymentResource($payment->load('order')),
        ]);
    }

    /**
     * Initialize a new payment for an order.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        // Rate limiting: max 5 payment initiations per minute per user
        $key = 'payment-initiate:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many payment attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $order = $request->getOrder();

        // Validate order can be paid
        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'This order has already been paid.',
            ], 422);
        }

        if ($order->total <= 0) {
            return response()->json([
                'message' => 'Order total must be greater than zero.',
            ], 422);
        }

        $result = $this->paystackService->initializeTransaction(
            $order,
            $request->user(),
            $request->input('callback_url')
        );

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
                'reference' => $result['data']['reference'],
            ],
            'payment' => new PaymentResource($result['payment']),
        ], 201);
    }

    /**
     * Verify a payment transaction.
     */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $reference = $request->validated('reference');

        // Rate limiting: max 10 verifications per minute per user
        $key = 'payment-verify:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many verification attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $result = $this->paystackService->verifyTransaction($reference);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
                'payment' => isset($result['payment']) ? new PaymentResource($result['payment']->load('order')) : null,
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
            'payment' => new PaymentResource($result['payment']->load('order')),
        ]);
    }

    /**
     * Handle Paystack callback (redirect from payment page).
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        $orderId = $request->query('order_id');
        $type = $request->query('type', 'order');

        if (! $reference) {
            return $this->redirectToApp('failed', null, 'Payment reference is required', $type, $orderId);
        }

        // Find the payment
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return $this->redirectToApp('failed', $reference, 'Payment not found', $type, $orderId);
        }

        if ($payment->isSuccessful()) {
            Log::info('Payment already verified', [
                'reference' => $reference,
                'order_id' => $payment->order_id,
            ]);

            return $this->redirectToApp('success', $reference, null, $type, $payment->order_id ?? $orderId);
        }

        if ($payment->hasFailed()) {
            Log::info('Payment already failed', [
                'reference' => $reference,
                'status' => $payment->status,
                'order_id' => $payment->order_id,
            ]);

            return $this->redirectToApp(
                'failed',
                $reference,
                $payment->failure_reason ?: 'Payment has already failed.',
                $type,
                $payment->order_id ?? $orderId
            );
        }

        // Verify the payment
        $result = $this->paystackService->verifyTransaction($reference);

        if (! $result['success']) {
            Log::warning('Payment verification failed', [
                'reference' => $reference,
                'message' => $result['message'],
            ]);

            return $this->redirectToApp('failed', $reference, $result['message'], $type, $orderId);
        }

        Log::info('Payment verified successfully', [
            'reference' => $reference,
            'order_id' => $payment->order_id,
        ]);

        return $this->redirectToApp('success', $reference, null, $type, $payment->order_id ?? $orderId);
    }

    /**
     * Redirect to mobile app via deep link.
     */
    private function redirectToApp(
        string $status,
        ?string $reference = null,
        ?string $message = null,
        string $type = 'order',
        ?int $orderId = null
    ) {
        $params = ['status' => $status, 'type' => $type];

        if ($reference) {
            $params['reference'] = $reference;
        }

        if ($orderId) {
            $params['order_id'] = $orderId;
        }

        if ($message && $status !== 'success') {
            $params['message'] = $message;
        }

        $deepLinkUrl = 'surprisemoi://payment-callback?'.http_build_query($params);

        Log::info('Redirecting to mobile app', [
            'deep_link' => $deepLinkUrl,
            'status' => $status,
        ]);

        return redirect($deepLinkUrl);
    }

    /**
     * Handle Paystack webhook.
     * This endpoint is called by Paystack to notify us of payment events.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Get the raw payload for signature verification
        $payload = $request->getContent();
        $signature = $request->header('X-Paystack-Signature', '');

        // Validate webhook signature
        if (! $this->paystackService->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true);

        if (! $data || ! isset($data['event'])) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $result = $this->paystackService->handleWebhook($data);

        return response()->json($result);
    }

    /**
     * Get payment status for an order.
     */
    public function orderPaymentStatus(Request $request, int $orderId): JsonResponse
    {
        $payment = Payment::where('order_id', $orderId)
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'No payment found for this order.',
                'payment_status' => 'unpaid',
            ], 404);
        }

        return response()->json([
            'payment' => new PaymentResource($payment->load('order')),
            'payment_status' => $payment->status,
        ]);
    }

    /**
     * Retry a failed payment.
     */
    public function retry(Request $request, Payment $payment): JsonResponse
    {
        // Ensure user owns this payment
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Can only retry failed/abandoned payments
        if (! $payment->hasFailed()) {
            return response()->json([
                'message' => 'Only failed or abandoned payments can be retried.',
            ], 422);
        }

        $order = $payment->order;

        // Check order is still valid
        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'This order has already been paid.',
            ], 422);
        }

        // Cancel the old payment
        $payment->update(['status' => Payment::STATUS_CANCELLED]);

        // Initialize a new payment
        $result = $this->paystackService->initializeTransaction(
            $order,
            $request->user()
        );

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'New payment initialized successfully.',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
                'reference' => $result['data']['reference'],
            ],
            'payment' => new PaymentResource($result['payment']),
        ], 201);
    }
}
