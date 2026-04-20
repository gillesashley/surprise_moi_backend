<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVendorVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'field_agent';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'storefront_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'owner_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'escalated' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'storefront_photo.max' => 'The storefront photo must be 5MB or smaller.',
            'owner_photo.max' => 'The owner photo must be 5MB or smaller.',
        ];
    }
}
