<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorTransaction;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PaystackService - Handles payment processing through Paystack gateway.
 *
 * This service manages:
 * - Payment initialization and verification
 * - Webhook event handling
 * - Refund processing
 * - Bank account listing
 * - Referral commission calculation on successful payments
 */
class PaystackService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected string $publicKey;

    protected string $currency;

    public function __construct(
        protected ReferralService $referralService,
        protected VendorBalanceService $vendorBalanceService
    ) {
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = config('services.paystack.secret_key') ?? '';
        $this->publicKey = config('services.paystack.public_key') ?? '';
        $this->currency = config('services.paystack.currency', 'GHS');

        // Warn if API keys are not configured
        if (empty($this->secretKey) || empty($this->publicKey)) {
            Log::warning('Paystack API keys are not configured. Payment features will not work.');
        }
    }

    /**
     * Initialize a payment transaction with Paystack.
     *
     * This method:
     * 1. Checks for existing pending payments to avoid duplicates
     * 2. Converts amount to kobo/pesewas (smallest currency unit)
     * 3. Calls Paystack API to get authorization URL
     * 4. Creates Payment record in database
     * 5. Returns authorization URL for customer to complete payment
     *
     * @param  Order  $order  The order being paid for
     * @param  User  $user  The customer making the payment
     * @param  string|null  $callbackUrl  URL to redirect customer after payment
     * @param  array<string, mixed>  $metadata  Additional data to store with payment
     * @return array{success: bool, data?: array<string, mixed>, message?: string, payment?: Payment}
     */
    public function initializeTransaction(
        Order $order,
        User $user,
        ?string $callbackUrl = null,
        array $metadata = []
    ): array {
        // Check if there's already a pending payment for this order
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if ($existingPayment) {
            // Return existing payment authorization URL if still valid
            // This prevents duplicate payments for the same order
            return [
                'success' => true,
                'data' => [
                    'authorization_url' => $existingPayment->authorization_url,
                    'access_code' => $existingPayment->access_code,
                    'reference' => $existingPayment->reference,
                ],
                'payment' => $existingPayment,
                'message' => 'Using existing pending payment.',
            ];
        }

        // Generate a unique reference for this transaction
        $reference = Payment::generateReference();

        // Convert amount to kobo/pesewas (smallest currency unit)
        // Paystack requires amounts in smallest unit (1 GHS = 100 pesewas)
        $amountInKobo = (int) round($order->total * 100);

        // Build metadata to attach to transaction
        // This data will be returned in webhooks and can be viewed in Paystack dashboard
        $paymentMetadata = array_merge([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $user->id,
            'custom_fields' => [
                [
                    'display_name' => 'Order Number',
                    'variable_name' => 'order_number',
                    'value' => $order->order_number,
                ],
                [
                    'display_name' => 'Customer Name',
                    'variable_name' => 'customer_name',
                    'value' => $user->name,
                ],
            ],
        ], $metadata);

        // Prepare request payload for Paystack API
        $payload = [
            'email' => $user->email,
            'amount' => $amountInKobo,
            'currency' => $this->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl ?? config('services.paystack.callback_url'),
            'metadata' => $paymentMetadata,
            'channels' => ['card', 'bank', 'mobile_money'],  // Allowed payment methods
        ];

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->post("{$this->baseUrl}/transaction/initialize", $payload);

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data');

                // Create payment record
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'reference' => $reference,
                    'authorization_url' => $data['authorization_url'],
                    'access_code' => $data['access_code'],
                    'amount' => $order->total,
                    'amount_in_kobo' => $amountInKobo,
                    'currency' => $this->currency,
                    'status' => Payment::STATUS_PENDING,
                    'ip_address' => request()->ip(),
                    'metadata' => $paymentMetadata,
                ]);

                // Update order payment status
                $order->update(['payment_status' => 'pending']);

                return [
                    'success' => true,
                    'data' => $data,
                    'payment' => $payment,
                    'message' => 'Payment initialized successfully.',
                ];
            }

            Log::error('Paystack initialization failed', [
                'response' => $response->json(),
                'order_id' => $order->id,
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to initialize payment.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack API request failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Verify a payment transaction with Paystack.
     *
     * Called after customer completes payment to confirm transaction status.
     * This method:
     * 1. Fetches payment record by reference
     * 2. Calls Paystack verification endpoint
     * 3. Validates amount matches expected total
     * 4. Updates payment and order status
     * 5. Calculates referral commissions if applicable
     *
     * @param  string  $reference  Unique payment reference to verify
     * @return array{success: bool, data?: array<string, mixed>, message?: string, payment?: Payment}
     */
    public function verifyTransaction(string $reference): array
    {
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return [
                'success' => false,
                'message' => 'Payment record not found.',
            ];
        }

        // If already verified and successful, return early to avoid duplicate processing
        if ($payment->isSuccessful()) {
            return [
                'success' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                ],
                'payment' => $payment,
                'message' => 'Payment already verified.',
            ];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if (! $response->successful()) {
                Log::error('Paystack verification request failed', [
                    'reference' => $reference,
                    'response' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to verify payment with payment provider.',
                ];
            }

            $responseData = $response->json();

            if ($responseData['status'] !== true) {
                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Payment verification failed.',
                ];
            }

            $data = $responseData['data'];

            return $this->processVerificationResponse($payment, $data);
        } catch (RequestException $e) {
            Log::error('Paystack verification API request failed', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification service is temporarily unavailable.',
            ];
        }
    }

    /**
     * Process the verification response and update payment/order.
     *
     * Handles:
     * - Extracting card/mobile money details
     * - Amount validation
     * - Payment status updates
     * - Order confirmation
     * - Referral commission calculation
     *
     * @param  Payment  $payment  The payment being verified
     * @param  array<string, mixed>  $data  Response data from Paystack
     * @return array{success: bool, data?: array<string, mixed>, message?: string, payment?: Payment}
     */
    protected function processVerificationResponse(Payment $payment, array $data): array
    {
        $paystackStatus = $data['status'] ?? '';
        $gatewayResponse = $data['gateway_response'] ?? '';
        $channel = $data['channel'] ?? null;  // card, bank, mobile_money, etc.

        // Extract card details if available (for card payments)
        $authorization = $data['authorization'] ?? [];
        $cardDetails = [];

        if (! empty($authorization)) {
            $cardDetails = [
                'card_last4' => $authorization['last4'] ?? null,
                'card_type' => $authorization['card_type'] ?? null,
                'card_exp_month' => $authorization['exp_month'] ?? null,
                'card_exp_year' => $authorization['exp_year'] ?? null,
                'card_bank' => $authorization['bank'] ?? null,
            ];

            // Check for mobile money - use null coalescing to avoid undefined key error
            $authChannel = $authorization['channel'] ?? null;
            if ($authChannel === 'mobile_money') {
                $cardDetails['mobile_money_number'] = $authorization['mobile_money_number'] ?? null;
                $cardDetails['mobile_money_provider'] = $authorization['bank'] ?? null;
            }
        }

        // Prepare update data with verification details
        $updateData = array_merge([
            'paystack_reference' => $data['reference'] ?? null,
            'channel' => $channel,
            'payment_method_type' => $authorization['card_type'] ?? $authorization['bank'] ?? null,
            'gateway_response' => $gatewayResponse,
            'log' => $data['log'] ?? null,
            'metadata' => array_merge($payment->metadata ?? [], ['verification_data' => $data]),
            'verified_at' => now(),
        ], $cardDetails);

        if ($paystackStatus === 'success') {
            // Verify amount matches to prevent fraud
            $expectedAmount = $payment->amount_in_kobo;
            $receivedAmount = $data['amount'] ?? 0;

            if ($receivedAmount < $expectedAmount) {
                Log::warning('Payment amount mismatch', [
                    'reference' => $payment->reference,
                    'expected' => $expectedAmount,
                    'received' => $receivedAmount,
                ]);

                $payment->markAsFailed('Amount mismatch detected.', $updateData);

                return [
                    'success' => false,
                    'message' => 'Payment amount does not match order total.',
                    'payment' => $payment->fresh(),
                ];
            }

            // Mark payment as successful
            $payment->markAsSuccessful($updateData);

            // Update order payment status
            $payment->order->update(['payment_status' => 'paid']);

            // Confirm the order if it was pending
            if ($payment->order->status === 'pending') {
                $payment->order->markAsConfirmed();
            }

            // Credit vendor's pending balance
            try {
                $this->vendorBalanceService->creditPendingBalance($payment->order);
            } catch (\Exception $e) {
                Log::warning('Failed to credit vendor balance for order', [
                    'order_id' => $payment->order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Calculate and record commission for influencer referrals
            try {
                // Find active referral for this vendor
                $referral = \App\Models\Referral::where('vendor_id', $payment->order->vendor_id)
                    ->withActiveCommission()
                    ->first();

                if ($referral) {
                    $this->referralService->calculateCommission($referral, $payment->order->total);
                }
            } catch (\Exception $e) {
                // Log but don't fail payment if commission calculation fails
                Log::warning('Failed to calculate referral commission for order', [
                    'order_id' => $payment->order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'channel' => $channel,
                    'paid_at' => $payment->fresh()->paid_at?->toIso8601String(),
                ],
                'payment' => $payment->fresh(),
                'message' => 'Payment verified successfully.',
            ];
        }

        if ($paystackStatus === Payment::STATUS_PENDING) {
            return [
                'success' => false,
                'data' => [
                    'status' => $paystackStatus,
                    'reference' => $payment->reference,
                ],
                'payment' => $payment->fresh(),
                'message' => 'Payment is still processing. Please wait.',
            ];
        }

        // Handle failed/abandoned/other statuses
        $failureStatus = match ($paystackStatus) {
            'abandoned' => Payment::STATUS_ABANDONED,
            'failed' => Payment::STATUS_FAILED,
            default => Payment::STATUS_FAILED,
        };

        $payment->update(array_merge($updateData, [
            'status' => $failureStatus,
            'failure_reason' => $gatewayResponse ?: "Payment {$paystackStatus}",
        ]));

        // Only update order payment_status to 'failed' if there's no successful payment
        // This prevents race conditions where delayed webhooks overwrite successful payments
        if (! $payment->order->payments()->where('status', Payment::STATUS_SUCCESS)->exists()) {
            $payment->order->update(['payment_status' => 'failed']);
        }

        return [
            'success' => false,
            'data' => [
                'status' => $paystackStatus,
                'reference' => $payment->reference,
            ],
            'payment' => $payment->fresh(),
            'message' => $gatewayResponse ?: "Payment {$paystackStatus}.",
        ];
    }

    /**
     * Handle Paystack webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        Log::info('Paystack webhook received', ['event' => $event, 'reference' => $data['reference'] ?? null]);

        return match ($event) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed' => $this->handleChargeFailed($data),
            'transfer.success' => $this->handleTransferSuccess($data),
            'transfer.failed' => $this->handleTransferFailed($data),
            default => ['success' => true, 'message' => 'Event ignored.'],
        };
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = config('services.paystack.webhook_secret');

        if (empty($webhookSecret)) {
            Log::warning('Paystack webhook secret not configured');

            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Handle charge.success webhook event.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string}
     */
    protected function handleChargeSuccess(array $data): array
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return ['success' => false, 'message' => 'No reference in webhook data.'];
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            // Try to find by paystack reference
            $payment = Payment::where('paystack_reference', $reference)->first();
        }

        if (! $payment) {
            Log::warning('Payment not found for webhook', ['reference' => $reference]);

            return ['success' => false, 'message' => 'Payment not found.'];
        }

        // Skip if already processed
        if ($payment->isSuccessful()) {
            return ['success' => true, 'message' => 'Payment already processed.'];
        }

        // Process the webhook data
        $this->processVerificationResponse($payment, $data);

        return ['success' => true, 'message' => 'Payment processed successfully.'];
    }

    /**
     * Handle charge.failed webhook event.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string}
     */
    protected function handleChargeFailed(array $data): array
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return ['success' => false, 'message' => 'No reference in webhook data.'];
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }

        if ($payment->hasFailed() || $payment->isSuccessful()) {
            return ['success' => true, 'message' => 'Payment already processed.'];
        }

        $gatewayResponse = $data['gateway_response'] ?? 'Payment failed';

        $payment->markAsFailed($gatewayResponse, [
            'channel' => $data['channel'] ?? null,
            'gateway_response' => $gatewayResponse,
            'metadata' => array_merge($payment->metadata ?? [], ['webhook_data' => $data]),
        ]);

        // Only update order payment_status to 'failed' if there's no successful payment
        // This prevents race conditions where a delayed failure webhook overwrites a later success
        if (! $payment->order->payments()->where('status', Payment::STATUS_SUCCESS)->exists()) {
            $payment->order->update(['payment_status' => 'failed']);
        }

        return ['success' => true, 'message' => 'Payment failure recorded.'];
    }

    /**
     * Handle transfer.success webhook event (for vendor payouts).
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string}
     */
    protected function handleTransferSuccess(array $data): array
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return ['success' => false, 'message' => 'No reference in webhook data.'];
        }

        $payoutRequest = PayoutRequest::where('paystack_transfer_reference', $reference)
            ->orWhere('request_number', $reference)
            ->first();

        if (! $payoutRequest) {
            Log::warning('Payout request not found for transfer webhook', ['reference' => $reference]);

            return ['success' => false, 'message' => 'Payout request not found.'];
        }

        if ($payoutRequest->status === PayoutRequest::STATUS_PAID) {
            return ['success' => true, 'message' => 'Payout already processed.'];
        }

        DB::transaction(function () use ($payoutRequest, $data) {
            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $balance = $this->vendorBalanceService->getOrCreateBalance($payoutRequest->user_id);
            $balance->increment('total_withdrawn', $payoutRequest->amount);

            $payoutRequest->user->vendorTransactions()->create([
                'type' => VendorTransaction::TYPE_PAYOUT,
                'amount' => $payoutRequest->amount,
                'currency' => $payoutRequest->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => "Payout {$payoutRequest->request_number} completed via Paystack",
                'metadata' => [
                    'paystack_reference' => $data['reference'] ?? null,
                    'paystack_transfer_code' => $data['transfer_code'] ?? null,
                ],
            ]);
        });

        Log::info('Transfer success processed', ['reference' => $reference, 'payout_id' => $payoutRequest->id]);

        return ['success' => true, 'message' => 'Payout marked as paid.'];
    }

    /**
     * Handle transfer.failed webhook event.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string}
     */
    protected function handleTransferFailed(array $data): array
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return ['success' => false, 'message' => 'No reference in webhook data.'];
        }

        $payoutRequest = PayoutRequest::where('paystack_transfer_reference', $reference)
            ->orWhere('request_number', $reference)
            ->first();

        if (! $payoutRequest) {
            Log::warning('Payout request not found for failed transfer webhook', ['reference' => $reference]);

            return ['success' => false, 'message' => 'Payout request not found.'];
        }

        if (in_array($payoutRequest->status, [PayoutRequest::STATUS_PAID, PayoutRequest::STATUS_FAILED])) {
            return ['success' => true, 'message' => 'Payout already processed.'];
        }

        DB::transaction(function () use ($payoutRequest, $data) {
            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_FAILED,
            ]);

            // Refund vendor balance
            $balance = $this->vendorBalanceService->getOrCreateBalance($payoutRequest->user_id);
            $balance->increment('available_balance', $payoutRequest->amount);

            $payoutRequest->user->vendorTransactions()->create([
                'type' => VendorTransaction::TYPE_REFUND,
                'amount' => $payoutRequest->amount,
                'currency' => $payoutRequest->currency,
                'status' => VendorTransaction::STATUS_COMPLETED,
                'description' => "Payout {$payoutRequest->request_number} failed - balance refunded",
                'metadata' => [
                    'paystack_reference' => $data['reference'] ?? null,
                    'failure_reason' => $data['gateway_response'] ?? 'Transfer failed',
                ],
            ]);
        });

        Log::warning('Transfer failed processed', ['reference' => $reference, 'payout_id' => $payoutRequest->id]);

        return ['success' => true, 'message' => 'Payout failure recorded, balance refunded.'];
    }

    /**
     * Get a list of available payment channels/banks.
     *
     * @return array{success: bool, data?: array<int, mixed>, message?: string}
     */
    public function getBanks(?string $country = 'ghana'): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->get("{$this->baseUrl}/bank", [
                    'country' => $country,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch banks.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack banks API request failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable.',
            ];
        }
    }

    /**
     * Refund a successful payment (if supported).
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function refundPayment(Payment $payment, ?int $amountInKobo = null): array
    {
        if (! $payment->isSuccessful()) {
            return [
                'success' => false,
                'message' => 'Can only refund successful payments.',
            ];
        }

        $payload = [
            'transaction' => $payment->reference,
        ];

        if ($amountInKobo) {
            $payload['amount'] = $amountInKobo;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->post("{$this->baseUrl}/refund", $payload);

            if ($response->successful() && $response->json('status') === true) {
                $payment->update([
                    'status' => Payment::STATUS_REVERSED,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'refund_data' => $response->json('data'),
                    ]),
                ]);

                $payment->order->update(['payment_status' => 'refunded']);

                return [
                    'success' => true,
                    'data' => $response->json('data'),
                    'message' => 'Refund initiated successfully.',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Refund failed.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack refund API request failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Refund service is temporarily unavailable.',
            ];
        }
    }

    /**
     * List banks/mobile money providers for a given currency.
     *
     * @return array{success: bool, data?: array<int, mixed>, message?: string}
     */
    public function listBanks(string $currency = 'GHS'): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get("{$this->baseUrl}/bank", [
                    'currency' => $currency,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to fetch banks.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack list banks failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable.',
            ];
        }
    }

    /**
     * Resolve/verify a bank account number.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get("{$this->baseUrl}/bank/resolve", [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to resolve account.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack resolve account failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Account verification service temporarily unavailable.',
            ];
        }
    }

    /**
     * Create a transfer recipient on Paystack.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function createTransferRecipient(
        string $type,
        string $name,
        string $accountNumber,
        string $bankCode,
        string $currency = 'GHS'
    ): array {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/transferrecipient", [
                    'type' => $type,
                    'name' => $name,
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'currency' => $currency,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to create transfer recipient.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack create recipient failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable.',
            ];
        }
    }

    /**
     * Delete (deactivate) a transfer recipient.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteTransferRecipient(string $recipientCode): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->delete("{$this->baseUrl}/transferrecipient/{$recipientCode}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Transfer recipient deactivated.',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to delete recipient.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack delete recipient failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable.',
            ];
        }
    }

    /**
     * Check Paystack balance.
     *
     * @return array{success: bool, data?: array<int, mixed>, message?: string}
     */
    public function checkBalance(): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get("{$this->baseUrl}/balance");

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to check balance.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack check balance failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable.',
            ];
        }
    }

    /**
     * Initiate a transfer to a recipient.
     * Amount is in pesewas (1 GHS = 100 pesewas).
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function initiateTransfer(
        int $amount,
        string $recipientCode,
        string $reason,
        string $reference
    ): array {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/transfer", [
                    'source' => 'balance',
                    'amount' => $amount,
                    'recipient' => $recipientCode,
                    'reason' => $reason,
                    'reference' => $reference,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                    'message' => $response->json('message'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to initiate transfer.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack initiate transfer failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Transfer service temporarily unavailable.',
            ];
        }
    }

    /**
     * Finalize a transfer with OTP.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function finalizeTransfer(string $transferCode, string $otp): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->post("{$this->baseUrl}/transfer/finalize_transfer", [
                    'transfer_code' => $transferCode,
                    'otp' => $otp,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                    'message' => $response->json('message'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to finalize transfer.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack finalize transfer failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Transfer service temporarily unavailable.',
            ];
        }
    }

    /**
     * Verify a transfer status by reference.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function verifyTransfer(string $reference): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->withOptions(['verify' => false])
                ->get("{$this->baseUrl}/transfer/verify/{$reference}");

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to verify transfer.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack verify transfer failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Transfer verification temporarily unavailable.',
            ];
        }
    }
}
