<?php

namespace App\Http\Requests\Api\V1\VendorRegistration;

use Illuminate\Foundation\Http\FormRequest;

class StoreBespokeServicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', 'exists:bespoke_services,id'],
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
            'service_ids.required' => 'Please select at least one service you can provide.',
            'service_ids.array' => 'The services must be provided as an array.',
            'service_ids.min' => 'Please select at least one service you can provide.',
            'service_ids.*.exists' => 'One or more selected services are invalid.',
        ];
    }
}
