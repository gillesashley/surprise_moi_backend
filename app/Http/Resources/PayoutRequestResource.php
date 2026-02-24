<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'user_role' => $this->user_role,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'payout_method' => $this->payout_method,
            'mobile_money_number' => $this->when($this->payout_method === 'mobile_money', $this->mobile_money_number),
            'mobile_money_provider' => $this->when($this->payout_method === 'mobile_money', $this->mobile_money_provider),
            'bank_name' => $this->when($this->payout_method === 'bank_transfer', $this->bank_name),
            'account_number' => $this->when($this->payout_method === 'bank_transfer', $this->account_number),
            'account_name' => $this->when($this->payout_method === 'bank_transfer', $this->account_name),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'processed_at' => $this->processed_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'processed_by' => $this->whenLoaded('processedBy', fn () => [
                'id' => $this->processedBy->id,
                'name' => $this->processedBy->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
