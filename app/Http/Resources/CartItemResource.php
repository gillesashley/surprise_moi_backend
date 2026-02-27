<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CartItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit_price_cents' => $this->unit_price_cents,
            'quantity' => $this->quantity,
            'line_total_cents' => $this->line_total_cents,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'metadata' => $this->metadata,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'thumbnail' => $this->product->thumbnail ? Storage::url($this->product->thumbnail) : null,
                    'stock' => $this->product->stock,
                    'is_available' => $this->product->is_available,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
