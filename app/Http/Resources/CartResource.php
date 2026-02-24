<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'user_id' => $this->user_id,
            'cart_token' => $this->cart_token,
            'currency' => $this->currency,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'subtotal_cents' => $this->subtotal_cents,
            'shipping_cents' => $this->shipping_cents,
            'tax_cents' => $this->tax_cents,
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->total_cents,
            'subtotal' => $this->subtotal,
            'shipping' => $this->shipping,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'version' => $this->version,
            'items_count' => $this->items->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
