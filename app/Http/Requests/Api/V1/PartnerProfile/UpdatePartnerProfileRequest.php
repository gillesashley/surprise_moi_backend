<?php

namespace App\Http\Requests\Api\V1\PartnerProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'temperament' => ['nullable', 'string', 'max:100'],
            'likes' => ['nullable', 'array'],
            'likes.*' => ['string', 'max:100'],
            'dislikes' => ['nullable', 'array'],
            'dislikes.*' => ['string', 'max:100'],
            'relationship_type' => ['nullable', 'string', 'max:50'],
            'age_range' => ['nullable', 'string', 'max:20'],
            'occasion' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'The name cannot be longer than 255 characters.',
            'notes.max' => 'The notes cannot be longer than 1000 characters.',
        ];
    }
}
