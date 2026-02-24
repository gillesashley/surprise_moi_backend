<?php

namespace App\Http\Requests\Api\V1\VendorRegistration;

use Illuminate\Foundation\Http\FormRequest;

class StoreGhanaCardRequest extends FormRequest
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
            'ghana_card_front' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'ghana_card_back' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
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
            'ghana_card_front.required' => 'Please upload the front of your Ghana Card.',
            'ghana_card_front.image' => 'The front of Ghana Card must be an image.',
            'ghana_card_front.mimes' => 'The front of Ghana Card must be a JPEG, PNG, or JPG file.',
            'ghana_card_front.max' => 'The front of Ghana Card must not exceed 5MB.',
            'ghana_card_back.required' => 'Please upload the back of your Ghana Card.',
            'ghana_card_back.image' => 'The back of Ghana Card must be an image.',
            'ghana_card_back.mimes' => 'The back of Ghana Card must be a JPEG, PNG, or JPG file.',
            'ghana_card_back.max' => 'The back of Ghana Card must not exceed 5MB.',
        ];
    }
}
