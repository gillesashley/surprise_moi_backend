<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TierUpgradeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_amount' => $this->payment_amount_in_ghs,
            'currency' => $this->payment_currency,
            'payment_verified_at' => $this->payment_verified_at?->toISOString(),
            'business_certificate_document' => $this->business_certificate_document
                ? storage_url($this->business_certificate_document)
                : null,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
