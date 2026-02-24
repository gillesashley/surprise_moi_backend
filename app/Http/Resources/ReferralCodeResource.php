<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'max_usages' => $this->max_usages,
            'registration_bonus' => (float) $this->registration_bonus,
            'commission_rate' => (float) $this->commission_rate,
            'commission_duration_months' => $this->commission_duration_months,
            'expires_at' => $this->expires_at?->toISOString(),
            'is_valid' => $this->isValid(),
            'has_reached_max_usages' => $this->hasReachedMaxUsages(),
            'influencer' => $this->whenLoaded('influencer', fn () => [
                'id' => $this->influencer->id,
                'name' => $this->influencer->name,
                'email' => $this->influencer->email,
            ]),
            'referrals_count' => $this->whenCounted('referrals'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
