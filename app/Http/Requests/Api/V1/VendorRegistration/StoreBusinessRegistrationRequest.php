<?php

namespace App\Http\Requests\Api\V1\VendorRegistration;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRegistrationRequest extends FormRequest
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
            'has_business_certificate' => ['required', 'boolean'],
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
            'has_business_certificate.required' => 'Please indicate if you have a business certificate.',
            'has_business_certificate.boolean' => 'The business certificate field must be true or false.',
        ];
    }
}
