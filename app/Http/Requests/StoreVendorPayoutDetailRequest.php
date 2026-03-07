<?php

namespace App\Http\Requests;

use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorPayoutDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'vendor';
    }

    public function rules(): array
    {
        return [
            'payout_method' => ['required', 'string', Rule::in([
                VendorPayoutDetail::METHOD_MOBILE_MONEY,
                VendorPayoutDetail::METHOD_BANK_TRANSFER,
            ])],
            'account_number' => ['required', 'string', 'max:20'],
            'bank_code' => ['required', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'in:mtn,vodafone,airteltigo'],
        ];
    }

    public function messages(): array
    {
        return [
            'payout_method.in' => 'Payout method must be mobile_money or bank_transfer.',
            'account_number.required' => 'Account number or phone number is required.',
            'bank_code.required' => 'Bank or provider code is required.',
        ];
    }
}
