<?php

namespace App\Http\Requests\Api\V1\VendorRegistration;

use App\Models\VendorApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnregisteredVendorDocumentsRequest extends FormRequest
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
            'selfie_image' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'mobile_money_number' => ['required', 'string', 'regex:/^0[0-9]{9}$/'],
            'mobile_money_provider' => ['required', 'string', Rule::in(VendorApplication::getMobileMoneyProviders())],
            'proof_of_business' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'],
            // Social media handles are optional
            'facebook_handle' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:255'],
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
            'selfie_image.required' => 'Please take a selfie for verification.',
            'selfie_image.image' => 'The selfie must be an image.',
            'selfie_image.mimes' => 'The selfie must be a JPEG, PNG, or JPG file.',
            'selfie_image.max' => 'The selfie must not exceed 5MB.',
            'mobile_money_number.required' => 'Please provide your Mobile Money number.',
            'mobile_money_number.regex' => 'Please enter a valid Ghanaian phone number (e.g., 0241234567).',
            'mobile_money_provider.required' => 'Please select your Mobile Money provider.',
            'mobile_money_provider.in' => 'Please select a valid Mobile Money provider (MTN, Vodafone, or AirtelTigo).',
            'proof_of_business.required' => 'Please upload proof of business activities.',
            'proof_of_business.file' => 'The proof of business must be a file.',
            'proof_of_business.mimes' => 'The proof of business must be a JPEG, PNG, JPG, or PDF file.',
            'proof_of_business.max' => 'The proof of business must not exceed 10MB.',
            'facebook_handle.max' => 'The Facebook handle must not exceed 255 characters.',
            'instagram_handle.max' => 'The Instagram handle must not exceed 255 characters.',
            'twitter_handle.max' => 'The Twitter handle must not exceed 255 characters.',
        ];
    }
}
