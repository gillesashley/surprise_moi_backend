<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPayoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mobile_money_number' => ['required', 'string', 'regex:/^\d{9,15}$/'],
            'mobile_money_provider' => ['required', Rule::in(['mtn', 'vodafone', 'airteltigo'])],
        ];
    }
}
