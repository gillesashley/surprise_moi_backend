<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VendorApplicationResource extends JsonResource
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
            'user_id' => $this->user_id,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'completed_step' => $this->completed_step,
            'is_editable' => $this->isEditable(),
            'can_submit' => $this->canSubmit(),
            'is_registered_vendor' => $this->isRegisteredVendor(),

            // Step 1: Ghana Card
            'ghana_card_front' => $this->ghana_card_front ? Storage::disk('public')->url($this->ghana_card_front) : null,
            'ghana_card_back' => $this->ghana_card_back ? Storage::disk('public')->url($this->ghana_card_back) : null,
            'step_1_completed' => $this->ghana_card_front && $this->ghana_card_back,

            // Step 2: Business Registration Flags
            'has_business_certificate' => $this->has_business_certificate,
            'step_2_completed' => $this->completed_step >= 2,

            // Step 3: Documents (conditionally included based on registration type)
            $this->mergeWhen($this->isRegisteredVendor(), [
                'business_certificate_document' => $this->business_certificate_document
                    ? Storage::disk('public')->url($this->business_certificate_document)
                    : null,
            ]),
            $this->mergeWhen(! $this->isRegisteredVendor(), [
                'selfie_image' => $this->selfie_image ? Storage::disk('public')->url($this->selfie_image) : null,
                'mobile_money_number' => $this->mobile_money_number,
                'mobile_money_provider' => $this->mobile_money_provider,
                'proof_of_business' => $this->proof_of_business ? Storage::disk('public')->url($this->proof_of_business) : null,
            ]),

            // Social Media (both types)
            'facebook_handle' => $this->facebook_handle,
            'instagram_handle' => $this->instagram_handle,
            'twitter_handle' => $this->twitter_handle,
            'step_3_completed' => $this->completed_step >= 3,

            // Step 4: Bespoke Services
            'bespoke_services' => BespokeServiceResource::collection($this->whenLoaded('bespokeServices')),
            'step_4_completed' => $this->completed_step >= 4,

            // Admin Review
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ];
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
