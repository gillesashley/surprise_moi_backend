<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WawVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'video_url' => storage_url($this->video_url),
            'thumbnail_url' => $this->thumbnail_url ? storage_url($this->thumbnail_url) : null,
            'caption' => $this->caption,
            'likes_count' => $this->likes_count,
            'views_count' => $this->views_count,
            'is_liked' => $this->relationLoaded('currentUserLike') && $this->currentUserLike !== null,
            'share_url' => config('app.url').'/waw/'.$this->id,
            'created_at' => $this->created_at,
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'name' => $this->vendor->name,
                'profile_image' => $this->vendor->avatar ? storage_url($this->vendor->avatar) : null,
            ]),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
        ];
    }
}
