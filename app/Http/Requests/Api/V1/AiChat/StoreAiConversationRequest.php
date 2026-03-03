<?php

namespace App\Http\Requests\Api\V1\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'partner_profile_id' => [
                'nullable',
                'integer',
                'exists:partner_profiles,id',
            ],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_profile_id.exists' => 'The selected partner profile does not exist.',
            'message.max' => 'The message cannot be longer than 2000 characters.',
        ];
    }

    /**
     * Additional validation: partner profile must belong to authenticated user.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->partner_profile_id) {
                $exists = $this->user()->partnerProfiles()
                    ->where('id', $this->partner_profile_id)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('partner_profile_id', 'This partner profile does not belong to you.');
                }
            }
        });
    }
}
