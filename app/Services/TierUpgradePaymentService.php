<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TierUpgradeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TierUpgradePaymentService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected string $currency;

    public function __construct()
    {
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = config('services.paystack.secret_key', '');
        $this->currency = config('services.paystack.currency', 'GHS');

        if (empty($this->secretKey)) {
            Log::warning('TierUpgradePaymentService: Paystack secret key is not configured.');
        }
    }

    /**
     * Calculate the upgrade fee (tier1 fee - tier2 fee) in GHS.
     */
    public function getUpgradeFee(): float
    {
        $tier1Fee = (float) Setting::get('vendor_tier1_onboarding_fee', 150);
        $tier2Fee = (float) Setting::get('vendor_tier2_onboarding_fee', 100);

        return max(0, $tier1Fee - $tier2Fee);
    }

    /**
     * Initialize a Paystack payment for the tier upgrade.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function initializePayment(User $vendor, ?string $callbackUrl = null): array
    {
        $upgradeFee = $this->getUpgradeFee();

        if ($upgradeFee <= 0) {
            return $this->createFreeUpgradeRequest($vendor);
        }

        $amountInPesewas = (int) round($upgradeFee * 100);
        $reference = TierUpgradeRequest::generateReference();

        $callbackUrl = $callbackUrl ?? route('api.v1.tier-upgrade.callback');

        $payload = [
            'email' => $vendor->email,
            'amount' => $amountInPesewas,
            'currency' => $this->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'type' => 'tier_upgrade',
                'current_tier' => $vendor->vendor_tier,
            ],
        ];

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Tier upgrade payment initialization failed', [
                'vendor_id' => $vendor->id,
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initialize payment. Please try again.',
            ];
        }

        $paystackData = $response->json('data');

        $upgradeRequest = DB::transaction(function () use ($vendor, $reference, $amountInPesewas) {
            return TierUpgradeRequest::create([
                'vendor_id' => $vendor->id,
                'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
                'payment_reference' => $reference,
                'payment_amount' => $amountInPesewas,
                'payment_currency' => $this->currency,
            ]);
        });

        return [
            'success' => true,
            'message' => 'Payment initialized successfully.',
            'data' => [
                'authorization_url' => $paystackData['authorization_url'],
                'access_code' => $paystackData['access_code'],
                'reference' => $reference,
                'amount' => $upgradeFee,
                'currency' => $this->currency,
            ],
        ];
    }

    /**
     * Create a free upgrade request (when fee difference is zero or negative).
     *
     * @return array{success: bool, message: string, data?: array}
     */
    private function createFreeUpgradeRequest(User $vendor): array
    {
        $upgradeRequest = TierUpgradeRequest::create([
            'vendor_id' => $vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_DOCUMENT,
            'payment_amount' => 0,
            'payment_currency' => $this->currency,
            'payment_verified_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Upgrade is free. Please submit your business certificate.',
            'data' => [
                'request_id' => $upgradeRequest->id,
                'status' => $upgradeRequest->status,
                'free_upgrade' => true,
            ],
        ];
    }

    /**
     * Verify a Paystack payment for the tier upgrade.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function verifyPayment(TierUpgradeRequest $upgradeRequest): array
    {
        if (! $upgradeRequest->isPendingPayment()) {
            return [
                'success' => false,
                'message' => 'This payment has already been processed.',
            ];
        }

        $reference = $upgradeRequest->payment_reference;

        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Tier upgrade payment verification failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification failed. Please try again.',
            ];
        }

        $data = $response->json('data');
        $paystackStatus = $data['status'] ?? 'failed';

        if ($paystackStatus !== 'success') {
            return [
                'success' => false,
                'message' => 'Payment was not successful: '.($data['gateway_response'] ?? 'Unknown error'),
            ];
        }

        DB::transaction(function () use ($upgradeRequest) {
            $upgradeRequest->update([
                'status' => TierUpgradeRequest::STATUS_PENDING_DOCUMENT,
                'payment_verified_at' => now(),
            ]);
        });

        return [
            'success' => true,
            'message' => 'Payment verified successfully. Please submit your business certificate.',
            'data' => [
                'request_id' => $upgradeRequest->id,
                'status' => $upgradeRequest->status,
            ],
        ];
    }

    /**
     * Validate a Paystack webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Process a webhook event for a tier upgrade payment.
     */
    public function handleWebhook(array $event): void
    {
        $eventType = $event['event'] ?? '';
        $data = $event['data'] ?? [];
        $reference = $data['reference'] ?? '';

        if (! str_starts_with($reference, 'TUP-')) {
            return;
        }

        if ($eventType !== 'charge.success') {
            return;
        }

        $upgradeRequest = TierUpgradeRequest::where('payment_reference', $reference)->first();

        if (! $upgradeRequest || ! $upgradeRequest->isPendingPayment()) {
            return;
        }

        DB::transaction(function () use ($upgradeRequest) {
            $upgradeRequest->update([
                'status' => TierUpgradeRequest::STATUS_PENDING_DOCUMENT,
                'payment_verified_at' => now(),
            ]);
        });

        Log::info('Tier upgrade payment verified via webhook', [
            'reference' => $reference,
            'vendor_id' => $upgradeRequest->vendor_id,
        ]);
    }
}
