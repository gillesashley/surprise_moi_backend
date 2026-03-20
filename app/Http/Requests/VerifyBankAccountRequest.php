<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'super_admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string'],
            'bank_code' => ['required', 'string'],
        ];
    }
}
