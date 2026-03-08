<?php

namespace App\Http\Requests\Api\V1\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:ios,android'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'A device token is required.',
            'token.max' => 'The device token must not exceed 255 characters.',
            'device_name.max' => 'The device name must not exceed 100 characters.',
            'platform.in' => 'The platform must be ios or android.',
        ];
    }
}
