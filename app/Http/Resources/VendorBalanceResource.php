<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorBalanceResource extends JsonResource
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
            'vendor_id' => $this->vendor_id,
            'vendor' => $this->when($this->relationLoaded('vendor'), function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'email' => $this->vendor->email,
                ];
            }),
            'pending_balance' => (float) $this->pending_balance,
            'available_balance' => (float) $this->available_balance,
            'total_balance' => (float) $this->total_balance,
            'total_earned' => (float) $this->total_earned,
            'total_withdrawn' => (float) $this->total_withdrawn,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
