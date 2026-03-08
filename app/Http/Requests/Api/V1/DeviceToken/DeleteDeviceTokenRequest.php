<?php

namespace App\Http\Requests\Api\V1\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'A device token is required.',
        ];
    }
}
