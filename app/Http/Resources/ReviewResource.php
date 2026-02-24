<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'images' => $this->images,
            'is_verified_purchase' => $this->is_verified_purchase,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'reviewable_type' => class_basename($this->reviewable_type),
            'reviewable_id' => $this->reviewable_id,
            'reviewable' => $this->when($this->relationLoaded('reviewable'), function () {
                return [
                    'id' => $this->reviewable->id,
                    'name' => $this->reviewable->name,
                    'thumbnail' => $this->reviewable->thumbnail ?? null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
