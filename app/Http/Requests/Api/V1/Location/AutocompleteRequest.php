<?php

namespace App\Http\Requests\Api\V1\Location;

use Illuminate\Foundation\Http\FormRequest;

class AutocompleteRequest extends FormRequest
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
            'query' => 'required|string|min:2|max:255',
            'country' => 'nullable|string|size:2|uppercase',
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
            'query.required' => 'Search query is required',
            'query.min' => 'Search query must be at least 2 characters',
            'country.size' => 'Country code must be 2 characters (ISO 3166-1 alpha-2)',
            'language.size' => 'Language code must be 2 characters (ISO 639-1)',
        ];
    }
}
