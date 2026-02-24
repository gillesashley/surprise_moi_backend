<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportAttachmentResource extends JsonResource
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
            'file_name' => $this->file_name,
            'file_size' => (int) $this->file_size,
            'mime_type' => $this->mime_type,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
