<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imagePaths = $this->whenLoaded('reviewImages', function () {
            return $this->reviewImages->pluck('storage_path')->all();
        });

        if (! is_array($imagePaths) || empty($imagePaths)) {
            $imagePaths = is_array($this->images) ? $this->images : [];
        }

        $images = collect($imagePaths)
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path): string => $this->absoluteUrl($path))
            ->values()
            ->all();

        $isHelpfulByMe = false;
        if (array_key_exists('is_helpful_by_me', $this->resource->getAttributes())) {
            $isHelpfulByMe = (bool) $this->resource->getAttribute('is_helpful_by_me');
        } elseif ($request->user()) {
            $isHelpfulByMe = $this->helpfuls()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        $helpfulCount = isset($this->helpfuls_count)
            ? (int) $this->helpfuls_count
            : (int) ($this->helpful_count ?? 0);

        $itemName = null;
        if ($this->relationLoaded('reviewable') && $this->reviewable) {
            $itemName = $this->reviewable->name;
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user?->id,
            'user_name' => $this->user?->name,
            'user_avatar' => $this->absoluteUrl($this->user?->avatar),
            'item_name' => $itemName,
            'item_id' => (int) $this->item_id,
            'item_type' => $this->item_type,
            'order_id' => $this->order_id ? (int) $this->order_id : null,
            'rating' => (float) $this->rating,
            'comment' => $this->comment,
            'images' => $images,
            'helpful_count' => $helpfulCount,
            'is_helpful_by_me' => $isHelpfulByMe,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $storagePath = Str::startsWith($path, '/')
            ? $path
            : Storage::url($path);

        if (Str::startsWith($storagePath, ['http://', 'https://'])) {
            return $storagePath;
        }

        return url($storagePath);
    }
}
