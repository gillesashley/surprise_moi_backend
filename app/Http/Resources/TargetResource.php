<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_role' => $this->user_role,
            'target_type' => $this->target_type,
            'target_value' => (float) $this->target_value,
            'current_value' => (float) $this->current_value,
            'bonus_amount' => (float) $this->bonus_amount,
            'overachievement_rate' => (float) $this->overachievement_rate,
            'period_type' => $this->period_type,
            'start_date' => $this->start_date->toISOString(),
            'end_date' => $this->end_date->toISOString(),
            'status' => $this->status,
            'completion_percentage' => $this->getCompletionPercentage(),
            'calculated_bonus' => $this->calculateTotalBonus(),
            'is_expired' => $this->isExpired(),
            'achieved_at' => $this->achieved_at?->toISOString(),
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
