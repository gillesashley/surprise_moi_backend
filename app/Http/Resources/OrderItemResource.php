<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'orderable_type' => class_basename($this->orderable_type),
            'orderable_id' => (int) $this->orderable_id,
            'variant_id' => $this->variant_id ? (int) $this->variant_id : null,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
            'currency' => $this->currency,
            'orderable' => $this->whenLoaded('orderable', function () {
                return [
                    'id' => (int) $this->orderable->id,
                    'name' => $this->orderable->name,
                    'thumbnail' => $this->orderable->thumbnail ? Storage::url($this->orderable->thumbnail) : null,
                ];
            }),
            'variant' => $this->whenLoaded('variant'),
            'snapshot' => is_string($this->snapshot) ? json_decode($this->snapshot, true) ?? [] : $this->snapshot ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
