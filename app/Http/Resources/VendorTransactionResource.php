<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorTransactionResource extends JsonResource
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
            'transaction_number' => $this->transaction_number,
            'vendor_id' => $this->vendor_id,
            'order_id' => $this->order_id,
            'order' => $this->when($this->relationLoaded('order') && $this->order !== null, function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                ];
            }),
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
