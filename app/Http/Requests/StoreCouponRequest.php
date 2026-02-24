<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'vendor' || $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed,cashback'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_limit_per_user' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date', 'before_or_equal:valid_until'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'applicable_to' => ['required', 'in:all,products,services,specific'],
            'specific_ids' => ['nullable', 'array', 'required_if:applicable_to,specific'],
            'specific_ids.*' => ['integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'The coupon code is required.',
            'code.unique' => 'This coupon code already exists.',
            'type.required' => 'The coupon type is required.',
            'type.in' => 'The coupon type must be percentage, fixed, or cashback.',
            'value.required' => 'The discount value is required.',
            'value.min' => 'The discount value must be at least 0.',
            'valid_from.before_or_equal' => 'The valid from date must be before or equal to the valid until date.',
            'valid_until.after_or_equal' => 'The valid until date must be after or equal to the valid from date.',
            'specific_ids.required_if' => 'Specific IDs are required when applicable_to is set to specific.',
        ];
    }
}
