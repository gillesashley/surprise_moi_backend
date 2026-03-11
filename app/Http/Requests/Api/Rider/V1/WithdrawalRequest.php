<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:1'],
            'mobile_money_provider' => ['required', 'in:mtn,vodafone,airteltigo'],
            'mobile_money_number' => ['required', 'string', 'max:20'],
        ];
    }
}
