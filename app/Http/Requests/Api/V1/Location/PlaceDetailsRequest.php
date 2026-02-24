<?php

namespace App\Http\Requests\Api\V1\Location;

use Illuminate\Foundation\Http\FormRequest;

class PlaceDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint - no authentication required
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'place_id' => 'required|string|max:255',
            'language' => 'nullable|string|size:2|lowercase',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'place_id.required' => 'Place ID is required',
            'language.size' => 'Language code must be 2 characters (ISO 639-1)',
        ];
    }
}
