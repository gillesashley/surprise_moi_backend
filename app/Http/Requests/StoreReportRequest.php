<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
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
            'category' => ['required', 'string', 'in:order_issue,product_problem,vendor_dispute,payment_issue,other'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'The selected category is invalid.',
            'description.min' => 'The description must be at least 10 characters.',
            'attachments.max' => 'You may upload a maximum of 5 attachments.',
            'attachments.*.mimes' => 'Each attachment must be a JPEG or PNG image.',
            'attachments.*.max' => 'Each attachment must not exceed 5MB.',
        ];
    }
}
