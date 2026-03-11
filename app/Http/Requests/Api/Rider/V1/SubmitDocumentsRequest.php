<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDocumentsRequest extends FormRequest
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
            'ghana_card_front' => ['required', 'image', 'max:5120'],
            'ghana_card_back' => ['required', 'image', 'max:5120'],
            'drivers_license' => ['required', 'image', 'max:5120'],
            'vehicle_photo' => ['required', 'image', 'max:5120'],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'license_plate' => ['required', 'string', 'max:20'],
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
            'ghana_card_front.max' => 'The front of Ghana Card must not exceed 5MB.',
            'ghana_card_back.required' => 'Please upload the back of your Ghana Card.',
            'ghana_card_back.image' => 'The back of Ghana Card must be an image.',
            'ghana_card_back.max' => 'The back of Ghana Card must not exceed 5MB.',
            'drivers_license.required' => 'Please upload your driver\'s license.',
            'drivers_license.image' => 'The driver\'s license must be an image.',
            'drivers_license.max' => 'The driver\'s license must not exceed 5MB.',
            'vehicle_photo.required' => 'Please upload a photo of your vehicle.',
            'vehicle_photo.image' => 'The vehicle photo must be an image.',
            'vehicle_photo.max' => 'The vehicle photo must not exceed 5MB.',
        ];
    }
}
