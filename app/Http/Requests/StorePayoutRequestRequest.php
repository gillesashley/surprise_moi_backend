<?php

namespace App\Http\Requests;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['influencer', 'field_agent', 'marketer']);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:10', 'max:1000000'],
            'payout_method' => ['required', 'string', Rule::in([
                PayoutRequest::METHOD_MOBILE_MONEY,
                PayoutRequest::METHOD_BANK_TRANSFER,
                PayoutRequest::METHOD_QUARTERLY_SALARY,
            ])],
            'mobile_money_number' => ['required_if:payout_method,mobile_money', 'string', 'max:15'],
            'mobile_money_provider' => ['required_if:payout_method,mobile_money', 'string', 'in:mtn,vodafone,airteltigo'],
            'bank_name' => ['required_if:payout_method,bank_transfer', 'string', 'max:100'],
            'account_number' => ['required_if:payout_method,bank_transfer', 'string', 'max:50'],
            'account_name' => ['required_if:payout_method,bank_transfer', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum payout amount is GHS 10.',
            'mobile_money_number.required_if' => 'Mobile money number is required for mobile money payouts.',
            'mobile_money_provider.required_if' => 'Mobile money provider is required for mobile money payouts.',
            'bank_name.required_if' => 'Bank name is required for bank transfer payouts.',
            'account_number.required_if' => 'Account number is required for bank transfer payouts.',
            'account_name.required_if' => 'Account name is required for bank transfer payouts.',
        ];
    }
}
