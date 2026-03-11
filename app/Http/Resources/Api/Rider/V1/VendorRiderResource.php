<?php

namespace App\Http\Resources\Api\Rider\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorRiderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'nickname' => $this->nickname,
            'is_default' => (bool) $this->is_default,
            'rider' => $this->whenLoaded('rider', fn () => new RiderResource($this->rider)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
