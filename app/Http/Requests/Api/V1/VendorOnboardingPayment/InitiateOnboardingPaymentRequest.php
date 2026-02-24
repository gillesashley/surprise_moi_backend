<?php

namespace App\Http\Requests\Api\V1\VendorOnboardingPayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class InitiateOnboardingPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'callback_url' => ['nullable', 'url', 'max:500'],
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
            'coupon_code.string' => 'The coupon code must be a valid text.',
            'coupon_code.max' => 'The coupon code cannot exceed 50 characters.',
            'callback_url.url' => 'The callback URL must be a valid URL.',
        ];
    }
}
