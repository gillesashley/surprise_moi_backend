<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalRequestResource extends JsonResource
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
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'mobile_money_provider' => $this->mobile_money_provider,
            'mobile_money_number' => $this->mobile_money_number,
            'processed_at' => $this->processed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
