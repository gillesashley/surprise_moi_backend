<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_role' => $this->user_role,
            'earning_type' => $this->earning_type,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'description' => $this->description,
            'earned_at' => $this->earned_at->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
