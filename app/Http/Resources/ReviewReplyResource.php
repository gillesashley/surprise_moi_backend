<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatar = $this->vendor?->avatar;

        if ($avatar && ! Str::startsWith($avatar, ['http://', 'https://', '/storage/'])) {
            $avatar = Storage::url($avatar);
        }

        return [
            'id' => $this->id,
            'review_id' => $this->review_id,
            'message' => $this->message,
            'vendor' => [
                'id' => $this->vendor?->id,
                'name' => $this->vendor?->name,
                'avatar' => $avatar,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
