<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'channel' => $this->channel,
            'payment_method_type' => $this->payment_method_type,

            // Masked card details (only for card payments)
            'card' => $this->when($this->channel === 'card' && $this->card_last4, [
                'last4' => $this->card_last4,
                'type' => $this->card_type,
                'bank' => $this->card_bank,
                'exp_month' => $this->card_exp_month,
                'exp_year' => $this->card_exp_year,
            ]),

            // Mobile money details
            'mobile_money' => $this->when($this->channel === 'mobile_money', [
                'provider' => $this->mobile_money_provider,
                'number' => $this->mobile_money_number ? $this->maskPhoneNumber($this->mobile_money_number) : null,
            ]),

            // Gateway response
            'gateway_response' => $this->gateway_response,
            'failure_reason' => $this->when($this->hasFailed(), $this->failure_reason),

            // Order details
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'total' => (float) $this->order->total,
                    'status' => $this->order->status,
                ];
            }),

            // Timestamps
            'paid_at' => $this->paid_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Mask phone number for privacy.
     */
    protected function maskPhoneNumber(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 4) {
            return $phone;
        }

        $visibleChars = 4;
        $masked = str_repeat('*', $length - $visibleChars);

        return $masked.substr($phone, -$visibleChars);
    }
}
