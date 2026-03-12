<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'delivery_fee' => (float) $this->delivery_fee,
            'total' => (float) $this->total,
            'platform_commission_rate' => (float) $this->platform_commission_rate,
            'platform_commission_amount' => (float) $this->platform_commission_amount,
            'vendor_payout_amount' => (float) $this->vendor_payout_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'can_be_paid' => $this->canBePaid(),
            'tracking_number' => $this->tracking_number,
            'special_instructions' => $this->special_instructions,
            'occasion' => $this->occasion,
            'scheduled_datetime' => $this->scheduled_datetime?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'items' => $this->when($this->relationLoaded('items'), function () {
                return OrderItemResource::collection($this->items);
            }, []),
            'delivery_address' => $this->whenLoaded('deliveryAddress', function () {
                return $this->deliveryAddress ? [
                    'id' => (int) $this->deliveryAddress->id,
                    'address_line' => $this->deliveryAddress->address_line,
                    'city' => $this->deliveryAddress->city,
                    'state' => $this->deliveryAddress->state,
                    'postal_code' => $this->deliveryAddress->postal_code,
                    'country' => $this->deliveryAddress->country,
                ] : null;
            }),
            'coupon' => $this->whenLoaded('coupon', function () {
                return $this->coupon ? new CouponResource($this->coupon) : null;
            }),
            'latest_payment' => $this->whenLoaded('latestPayment', function () {
                return $this->latestPayment ? new PaymentResource($this->latestPayment) : null;
            }),
            'vendor' => $this->whenLoaded('vendor', function () {
                return $this->vendor ? [
                    'id' => (int) $this->vendor->id,
                    'name' => $this->vendor->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
