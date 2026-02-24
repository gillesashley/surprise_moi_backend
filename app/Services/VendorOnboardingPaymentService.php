<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VendorOnboardingPaymentService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected string $publicKey;

    protected string $currency;

    public function __construct()
    {
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = config('services.paystack.secret_key') ?? '';
        $this->publicKey = config('services.paystack.public_key') ?? '';
        $this->currency = config('services.paystack.currency', 'GHS');

        if (empty($this->secretKey) || empty($this->publicKey)) {
            Log::warning('Paystack API keys are not configured. Payment features will not work.');
        }
    }

    /**
     * Validate a coupon code for vendor onboarding.
     */
    public function validateCoupon(string $code, VendorApplication $application): array
    {
        $coupon = Coupon::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $coupon) {
            return [
                'valid' => false,
                'message' => 'Invalid or inactive coupon code.',
            ];
        }

        if (! $coupon->isValid()) {
            return [
                'valid' => false,
                'message' => 'This coupon has expired or reached its usage limit.',
            ];
        }

        // Check if user can use this coupon
        if (! $coupon->canBeUsedBy($application->user)) {
            return [
                'valid' => false,
                'message' => 'You have already used this coupon the maximum number of times.',
            ];
        }

        // Calculate discount
        $onboardingFee = $application->getOnboardingFee();
        $discountAmount = $coupon->calculateDiscount($onboardingFee);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'onboarding_fee' => $onboardingFee,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $onboardingFee - $discountAmount),
            'message' => 'Coupon applied successfully.',
        ];
    }

    /**
     * Initialize vendor onboarding payment.
     */
    public function initializePayment(
        VendorApplication $application,
        ?string $couponCode = null,
        ?string $callbackUrl = null
    ): array {
        // Check if there's already a pending payment
        $existingPayment = VendorOnboardingPayment::where('vendor_application_id', $application->id)
            ->where('status', VendorOnboardingPayment::STATUS_PENDING)
            ->first();

        if ($existingPayment) {
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

        // Validate coupon if provided
        $coupon = null;
        $discountAmount = 0;
        if ($couponCode) {
            $couponValidation = $this->validateCoupon($couponCode, $application);
            if (! $couponValidation['valid']) {
                return [
                    'success' => false,
                    'message' => $couponValidation['message'],
                ];
            }
            $coupon = $couponValidation['coupon'];
            $discountAmount = $couponValidation['discount_amount'];
        }

        // Calculate amounts
        $amounts = $application->calculateFinalAmount($coupon);
        $onboardingFee = (float) $amounts['onboarding_fee'];
        $finalAmount = (float) $amounts['final_amount'];
        $discountAmount = (float) $amounts['discount_amount'];

        // Generate reference
        $reference = VendorOnboardingPayment::generateReference();

        // Convert to kobo/pesewas
        $amountInKobo = (int) round($finalAmount * 100);

        // Build metadata
        $metadata = [
            'vendor_application_id' => $application->id,
            'user_id' => $application->user_id,
            'vendor_tier' => $application->getVendorTier(),
            'onboarding_fee' => $onboardingFee,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'payment_type' => 'vendor_onboarding',
            'custom_fields' => [
                [
                    'display_name' => 'Vendor Name',
                    'variable_name' => 'vendor_name',
                    'value' => $application->user->name,
                ],
                [
                    'display_name' => 'Vendor Tier',
                    'variable_name' => 'vendor_tier',
                    'value' => $application->getVendorTier() === 1 ? 'Registered Business' : 'Individual Vendor',
                ],
            ],
        ];

        if ($coupon) {
            $metadata['coupon_code'] = $coupon->code;
            $metadata['coupon_id'] = $coupon->id;
        }

        // Prepare Paystack request
        $payload = [
            'email' => $application->user->email,
            'amount' => $amountInKobo,
            'currency' => $this->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl ?? route('api.v1.vendor-onboarding-payment.callback'),
            'metadata' => $metadata,
            'channels' => ['card', 'bank', 'mobile_money'],
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
                $payment = DB::transaction(function () use ($application, $coupon, $reference, $data, $onboardingFee, $discountAmount, $finalAmount, $amountInKobo, $metadata) {
                    $payment = VendorOnboardingPayment::create([
                        'user_id' => $application->user_id,
                        'vendor_application_id' => $application->id,
                        'coupon_id' => $coupon?->id,
                        'reference' => $reference,
                        'authorization_url' => $data['authorization_url'],
                        'access_code' => $data['access_code'],
                        'amount' => $finalAmount,
                        'amount_in_kobo' => $amountInKobo,
                        'discount_amount' => $discountAmount,
                        'currency' => $this->currency,
                        'status' => VendorOnboardingPayment::STATUS_PENDING,
                        'ip_address' => request()->ip(),
                        'metadata' => $metadata,
                    ]);

                    // Update application with payment details
                    $application->update([
                        'coupon_id' => $coupon?->id,
                        'onboarding_fee' => $onboardingFee,
                        'discount_amount' => $discountAmount,
                        'final_amount' => $finalAmount,
                    ]);

                    return $payment;
                });

                return [
                    'success' => true,
                    'data' => $data,
                    'payment' => $payment,
                    'message' => 'Payment initialized successfully.',
                ];
            }

            Log::error('Paystack initialization failed for vendor onboarding', [
                'response' => $response->json(),
                'application_id' => $application->id,
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to initialize payment.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack API request failed for vendor onboarding', [
                'error' => $e->getMessage(),
                'application_id' => $application->id,
            ]);

            return [
                'success' => false,
                'message' => 'Payment service is temporarily unavailable. Please try again.',
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during vendor onboarding payment initialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'application_id' => $application->id,
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ];
        }
    }

    /**
     * Verify vendor onboarding payment.
     */
    public function verifyPayment(VendorOnboardingPayment $payment): array
    {
        // If already verified and successful, return early
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
                ->get("{$this->baseUrl}/transaction/verify/{$payment->reference}");

            if (! $response->successful()) {
                Log::error('Paystack verification request failed for vendor onboarding', [
                    'reference' => $payment->reference,
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
            Log::error('Paystack verification API request failed for vendor onboarding', [
                'error' => $e->getMessage(),
                'reference' => $payment->reference,
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification service is temporarily unavailable.',
            ];
        }
    }

    /**
     * Process verification response and update payment/application.
     */
    protected function processVerificationResponse(VendorOnboardingPayment $payment, array $data): array
    {
        $paystackStatus = $data['status'] ?? '';
        $gatewayResponse = $data['gateway_response'] ?? '';
        $channel = $data['channel'] ?? null;

        // Extract card/payment method details
        $authorization = $data['authorization'] ?? [];
        $paymentDetails = [];

        if (! empty($authorization)) {
            $paymentDetails = [
                'card_last4' => $authorization['last4'] ?? null,
                'card_type' => $authorization['card_type'] ?? null,
                'card_exp_month' => $authorization['exp_month'] ?? null,
                'card_exp_year' => $authorization['exp_year'] ?? null,
                'card_bank' => $authorization['bank'] ?? null,
            ];

            $authChannel = $authorization['channel'] ?? null;
            if ($authChannel === 'mobile_money') {
                $paymentDetails['mobile_money_number'] = $authorization['mobile_money_number'] ?? null;
                $paymentDetails['mobile_money_provider'] = $authorization['bank'] ?? null;
            }
        }

        $updateData = array_merge([
            'paystack_reference' => $data['reference'] ?? null,
            'channel' => $channel,
            'payment_method_type' => $authorization['card_type'] ?? $authorization['bank'] ?? null,
            'gateway_response' => $gatewayResponse,
            'log' => $data['log'] ?? null,
            'metadata' => array_merge($payment->metadata ?? [], ['verification_data' => $data]),
            'verified_at' => now(),
        ], $paymentDetails);

        if ($paystackStatus === 'success') {
            // Verify amount matches
            $expectedAmount = $payment->amount_in_kobo;
            $receivedAmount = $data['amount'] ?? 0;

            if ($receivedAmount < $expectedAmount) {
                Log::warning('Vendor onboarding payment amount mismatch', [
                    'reference' => $payment->reference,
                    'expected' => $expectedAmount,
                    'received' => $receivedAmount,
                ]);

                $payment->markAsFailed('Amount mismatch detected.', $updateData);

                return [
                    'success' => false,
                    'message' => 'Payment amount does not match expected amount.',
                    'payment' => $payment->fresh(),
                ];
            }

            return DB::transaction(function () use ($payment, $updateData) {
                // Mark payment as successful
                $payment->markAsSuccessful($updateData);

                // Update vendor application
                $application = $payment->vendorApplication;
                $application->update([
                    'payment_completed' => true,
                    'payment_completed_at' => now(),
                ]);

                // Update coupon usage if applicable
                if ($payment->coupon_id) {
                    $payment->coupon->increment('used_count');
                    $payment->coupon->usages()->create([
                        'user_id' => $payment->user_id,
                        'order_id' => null,
                        'discount_amount' => $payment->discount_amount,
                        'used_at' => now(),
                    ]);
                }

                return [
                    'success' => true,
                    'data' => [
                        'status' => 'success',
                        'reference' => $payment->reference,
                        'amount' => $payment->amount,
                        'paid_at' => $payment->paid_at,
                    ],
                    'payment' => $payment->fresh(),
                    'message' => 'Payment verified successfully.',
                ];
            });
        } else {
            // Payment failed
            $failureReason = $gatewayResponse ?: 'Payment was not successful.';
            $payment->markAsFailed($failureReason, $updateData);

            return [
                'success' => false,
                'message' => "Payment {$paystackStatus}: {$failureReason}",
                'payment' => $payment->fresh(),
            ];
        }
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computedSignature, $signature);
    }
}
