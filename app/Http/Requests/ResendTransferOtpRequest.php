<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendTransferOtpRequest extends FormRequest
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
            'transfer_code' => ['required', 'string'],
        ];
    }
}
