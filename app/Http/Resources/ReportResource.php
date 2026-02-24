<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
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
            'report_number' => $this->report_number,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'order_id' => $this->order_id ? (int) $this->order_id : null,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'attachments' => $this->when(
                $this->relationLoaded('attachments'),
                fn () => ReportAttachmentResource::collection($this->attachments),
                [],
            ),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolver' => $this->whenLoaded('resolver', fn () => $this->resolver ? [
                'id' => (int) $this->resolver->id,
                'name' => $this->resolver->name,
            ] : null),
            'resolution_notes' => $this->resolution_notes,
            'cancellation_reason' => $this->cancellation_reason,
            'can_be_cancelled' => $this->canBeCancelled(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
