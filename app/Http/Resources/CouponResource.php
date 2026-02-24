<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_purchase_amount' => $this->min_purchase_amount ? (float) $this->min_purchase_amount : null,
            'max_discount_amount' => $this->max_discount_amount ? (float) $this->max_discount_amount : null,
            'currency' => $this->currency,
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'user_limit_per_user' => $this->user_limit_per_user,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'applicable_to' => $this->applicable_to,
            'specific_ids' => $this->specific_ids,
            'description' => $this->description,
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
