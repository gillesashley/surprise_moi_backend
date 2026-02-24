<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'earned_amount' => (float) $this->earned_amount,
            'is_commission_active' => $this->isCommissionActive(),
            'activated_at' => $this->activated_at?->toISOString(),
            'commission_expires_at' => $this->commission_expires_at?->toISOString(),
            'referral_code' => $this->whenLoaded('referralCode', fn () => new ReferralCodeResource($this->referralCode)),
            'influencer' => $this->whenLoaded('influencer', fn () => [
                'id' => $this->influencer->id,
                'name' => $this->influencer->name,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'email' => $this->vendor->email,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
