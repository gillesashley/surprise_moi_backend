<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderEarningResource extends JsonResource
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
            'order_id' => (int) $this->order_id,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'status' => $this->status,
            'available_at' => $this->available_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
