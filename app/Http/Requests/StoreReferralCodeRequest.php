<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isInfluencer();
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', 'unique:referral_codes,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'registration_bonus' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_duration_months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'max_usages' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This referral code is already taken.',
            'registration_bonus.max' => 'Registration bonus cannot exceed GHS 10,000.',
            'commission_rate.max' => 'Commission rate cannot exceed 100%.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
