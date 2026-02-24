<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $coupon = $this->route('coupon');

        return $this->user()->role === 'admin' ||
            ($this->user()->role === 'vendor' && $coupon->vendor_id === $this->user()->id);
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')->id;

        return [
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('coupons')->ignore($couponId)],
            'type' => ['sometimes', 'in:percentage,fixed,cashback'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_limit_per_user' => ['sometimes', 'integer', 'min:1'],
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['sometimes', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
            'applicable_to' => ['sometimes', 'in:all,products,services,specific'],
            'specific_ids' => ['nullable', 'array', 'required_if:applicable_to,specific'],
            'specific_ids.*' => ['integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This coupon code already exists.',
            'type.in' => 'The coupon type must be percentage, fixed, or cashback.',
            'value.min' => 'The discount value must be at least 0.',
            'valid_until.after_or_equal' => 'The valid until date must be after or equal to the valid from date.',
            'specific_ids.required_if' => 'Specific IDs are required when applicable_to is set to specific.',
        ];
    }
}
