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
        // Use input() instead of query() to handle both GET query params and POST body
        // (some 3DS flows redirect via POST instead of GET)
        $reference = $request->input('reference') ?? $request->input('trxref');
        $orderId = $request->input('order_id');
        $type = $request->input('type', 'order');

        Log::info('Payment callback received', [
            'reference' => $reference,
            'method' => $request->method(),
            'query_params' => $request->query(),
            'has_body' => $request->getContent() !== '',
            'user_agent' => $request->userAgent(),
        ]);

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
            'channel' => $result['data']['channel'] ?? 'unknown',
        ]);

        return $this->redirectToApp('success', $reference, null, $type, $payment->order_id ?? $orderId);
    }

    /**
     * Redirect to mobile app via deep link.
     *
     * Uses an HTML intermediate page with JavaScript instead of a bare 302 redirect.
     * This is necessary because mobile browsers (especially Chrome) may not follow
     * HTTP 302 redirects to custom URI schemes (surprisemoi://) after 3D Secure
     * cross-domain redirect chains used in card payments.
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
            'reference' => $reference,
        ]);

        $statusText = $status === 'success' ? 'Payment Successful' : 'Payment Failed';
        $statusMessage = $status === 'success'
            ? 'Your payment has been processed. Returning you to the app...'
            : ($message ?: 'Something went wrong with your payment.');
        $escapedDeepLink = htmlspecialchars($deepLinkUrl, ENT_QUOTES, 'UTF-8');
        $jsDeepLink = json_encode($deepLinkUrl, JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$statusText} - SurpriseMoi</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f5f5f5; padding: 20px; }
                .card { background: white; border-radius: 16px; padding: 40px 24px; text-align: center; max-width: 400px; width: 100%; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
                .icon { font-size: 48px; margin-bottom: 16px; }
                h1 { font-size: 20px; color: #1a1a1a; margin-bottom: 8px; }
                p { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.5; }
                .btn { display: inline-block; background: #6366f1; color: white; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: 600; }
                .btn:active { background: #4f46e5; }
                .spinner { width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
                @keyframes spin { to { transform: rotate(360deg); } }
                #fallback { display: none; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">{$this->getStatusEmoji($status)}</div>
                <h1>{$statusText}</h1>
                <p>{$statusMessage}</p>
                <div id="loading">
                    <div class="spinner"></div>
                    <p>Opening SurpriseMoi app...</p>
                </div>
                <div id="fallback">
                    <a href="{$escapedDeepLink}" class="btn">Open SurpriseMoi App</a>
                    <p style="margin-top: 16px; font-size: 12px; color: #999;">If the app doesn't open, please return to the SurpriseMoi app manually.</p>
                </div>
            </div>
            <script>
                (function() {
                    var deepLink = {$jsDeepLink};
                    // Attempt deep link via location change
                    window.location.href = deepLink;
                    // Show fallback button after 2 seconds if app didn't open
                    setTimeout(function() {
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('fallback').style.display = 'block';
                    }, 2000);
                })();
            </script>
        </body>
        </html>
        HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Get an emoji for the payment status.
     */
    private function getStatusEmoji(string $status): string
    {
        return match ($status) {
            'success' => '&#10003;&#65039;',
            default => '&#10060;',
        };
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
