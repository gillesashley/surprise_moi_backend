<?php

namespace App\Http\Requests\Api\V1\VendorRegistration;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegisteredVendorDocumentsRequest extends FormRequest
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
        $application = $this->user()->vendorApplications()->latest()->first();

        $rules = [];

        // Require business certificate document if they indicated they have one
        if ($application && $application->has_business_certificate) {
            $rules['business_certificate_document'] = ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'];
        }

        // Social media handles are optional
        $rules['facebook_handle'] = ['nullable', 'string', 'max:255'];
        $rules['instagram_handle'] = ['nullable', 'string', 'max:255'];
        $rules['twitter_handle'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_certificate_document.required' => 'Please upload your business certificate document.',
            'business_certificate_document.file' => 'The business certificate must be a file.',
            'business_certificate_document.mimes' => 'The business certificate must be a JPEG, PNG, JPG, or PDF file.',
            'business_certificate_document.max' => 'The business certificate must not exceed 10MB.',
            'facebook_handle.max' => 'The Facebook handle must not exceed 255 characters.',
            'instagram_handle.max' => 'The Instagram handle must not exceed 255 characters.',
            'twitter_handle.max' => 'The Twitter handle must not exceed 255 characters.',
        ];
    }
}
