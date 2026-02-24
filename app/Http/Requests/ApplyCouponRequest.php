<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'exists:coupons,code'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:product,service'],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Please enter a coupon code.',
            'code.exists' => 'This coupon code does not exist.',
            'subtotal.required' => 'The subtotal is required to apply the coupon.',
            'items.required' => 'At least one item is required to apply the coupon.',
        ];
    }
}
