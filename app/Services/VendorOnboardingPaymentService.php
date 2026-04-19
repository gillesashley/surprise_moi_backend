<?php

namespace App\Services;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use App\Notifications\VendorOnboardingPaidNotification;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VendorOnboardingPaymentService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected string $publicKey;

    protected string $currency;

    public function __construct(protected ReferralService $referralService)
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
     * Validate a referral code for vendor onboarding.
     *
     * When the code is valid, the returned amounts reflect the platform-wide
     * subsidy applied to the vendor's onboarding fee. The code is also tracked
     * on the application so the referrer can be rewarded with points on
     * approval.
     */
    public function validateReferralCode(string $code, VendorApplication $application): array
    {
        $referralCode = ReferralCode::where('code', $code)->first();

        if (! $referralCode || ! $referralCode->isValid()) {
            return [
                'valid' => false,
                'message' => 'Invalid, expired, or inactive referral code.',
            ];
        }

        // A vendor cannot use their own referral code.
        if ((int) $referralCode->influencer_id === (int) $application->user_id) {
            return [
                'valid' => false,
                'message' => 'You cannot use your own referral code.',
            ];
        }

        // If this application already has a different referral code attached,
        // block the swap to keep the influencer-vendor relationship stable.
        if (
            $application->referral_code_id
            && (int) $application->referral_code_id !== (int) $referralCode->id
        ) {
            return [
                'valid' => false,
                'message' => 'A different referral code has already been applied to this application.',
            ];
        }

        $amounts = $application->calculateFinalAmount($referralCode);

        return [
            'valid' => true,
            'referral_code' => $referralCode,
            'onboarding_fee' => $amounts['onboarding_fee'],
            'discount_amount' => $amounts['discount_amount'],
            'final_amount' => $amounts['final_amount'],
            'message' => 'Referral code applied successfully.',
        ];
    }

    /**
     * Initialize vendor onboarding payment.
     */
    public function initializePayment(
        VendorApplication $application,
        ?string $referralCode = null,
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

        // Validate referral code if provided. Referral codes do not discount
        // the fee — they track the referrer for reward on approval.
        $referralCodeModel = null;
        if ($referralCode) {
            $validation = $this->validateReferralCode($referralCode, $application);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }
            $referralCodeModel = $validation['referral_code'];
        }

        // Calculate amounts. Coupon path removed — vendor pays full fee.
        $amounts = $application->calculateFinalAmount(null);
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

        if ($referralCodeModel) {
            $metadata['referral_code'] = $referralCodeModel->code;
            $metadata['referral_code_id'] = $referralCodeModel->id;
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
                $payment = DB::transaction(function () use ($application, $referralCodeModel, $reference, $data, $onboardingFee, $discountAmount, $finalAmount, $amountInKobo, $metadata) {
                    $payment = VendorOnboardingPayment::create([
                        'user_id' => $application->user_id,
                        'vendor_application_id' => $application->id,
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
                        'onboarding_fee' => $onboardingFee,
                        'discount_amount' => $discountAmount,
                        'final_amount' => $finalAmount,
                    ]);

                    // Attach the referral code to the application so the referrer
                    // gets rewarded when the application is approved. Guarded so
                    // re-initialization on an already-referred application is a
                    // no-op (applyReferralCode throws otherwise).
                    if ($referralCodeModel && ! $application->referral_code_id) {
                        $this->referralService->applyReferralCode(
                            $application->refresh(),
                            $referralCodeModel->code
                        );
                    }

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
        // If already verified and successful, auto-submit if needed and return early
        if ($payment->isSuccessful()) {
            $application = $payment->vendorApplication;
            if ($application && $application->canSubmit()) {
                $application->submit();
            }

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

            $result = DB::transaction(function () use ($payment, $updateData) {
                // Mark payment as successful
                $payment->markAsSuccessful($updateData);

                // Update vendor application
                $application = $payment->vendorApplication;
                $application->update([
                    'payment_completed' => true,
                    'payment_completed_at' => now(),
                ]);

                // Auto-submit the application now that payment is complete
                $application->refresh();
                $autoSubmitted = $application->canSubmit() ? $application->submit() : false;


                return [
                    'application' => $application,
                    'auto_submitted' => $autoSubmitted,
                    'result' => [
                        'success' => true,
                        'data' => [
                            'status' => 'success',
                            'reference' => $payment->reference,
                            'amount' => $payment->amount,
                            'paid_at' => $payment->paid_at,
                        ],
                        'payment' => $payment->fresh(),
                        'message' => 'Payment verified successfully.',
                    ],
                ];
            });

            // Notify admins AFTER transaction commits so queued jobs can read committed data
            $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
            Notification::send($admins, new VendorOnboardingPaidNotification($result['application']));

            return $result['result'];
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
