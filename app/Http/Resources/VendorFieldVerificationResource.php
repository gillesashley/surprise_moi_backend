<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorFieldVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_field_verified' => $this->isFieldVerified(),
            'field_verified_at' => $this->field_verified_at?->toIso8601String(),
            'field_verified_until' => $this->field_verified_until?->toIso8601String(),
            'visits' => $this->vendorVisitsReceived()
                ->latest('started_at')
                ->get(['id', 'status', 'started_at'])
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'visited_at' => $v->started_at?->toIso8601String(),
                    'outcome' => $v->status,
                ]),
        ];
    }
}
