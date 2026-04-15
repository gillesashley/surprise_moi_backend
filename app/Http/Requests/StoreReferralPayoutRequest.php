<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payout_detail_id' => [
                'nullable',
                'integer',
                Rule::exists('user_payout_details', 'id')
                    ->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
